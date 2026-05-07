(function () {
    var root = document.getElementById('tenantRolloutPage');
    if (!root) return;

    var cfg = document.getElementById('control-config');
    var apiBase = (cfg && cfg.getAttribute('data-api-base')) || '';
    apiBase = apiBase.replace(/\/$/, '');
    if (!apiBase) return;

    var copyBtn = document.getElementById('copyRolloutLinkBtn');
    var flash = document.getElementById('tenantRolloutFlash');
    var urlEl = document.getElementById('tenantRolloutDirectUrl');

    var tenantForm = document.getElementById('tenantForm');
    var tenantIdInput = document.getElementById('tenantIdInput');
    var tenantCodeInput = document.getElementById('tenantCodeInput');
    var tenantNameInput = document.getElementById('tenantNameInput');
    var tenantDomainInput = document.getElementById('tenantDomainInput');
    var tenantCountryInput = document.getElementById('tenantCountryInput');
    var tenantDbKeyInput = document.getElementById('tenantDbKeyInput');
    var tenantStatusInput = document.getElementById('tenantStatusInput');
    var tenantFormResetBtn = document.getElementById('tenantFormResetBtn');
    var tenantRegistryList = document.getElementById('tenantRegistryList');
    var tenantRegistryPager = document.getElementById('tenantRegistryPager');
    var tenantSearchInput = document.getElementById('tenantSearchInput');

    var flagForm = document.getElementById('flagForm');
    var flagIdInput = document.getElementById('flagIdInput');
    var flagKeyInput = document.getElementById('flagKeyInput');
    var flagDescriptionInput = document.getElementById('flagDescriptionInput');
    var flagDefaultInput = document.getElementById('flagDefaultInput');
    var flagFormResetBtn = document.getElementById('flagFormResetBtn');
    var featureFlagsList = document.getElementById('featureFlagsList');
    var featureFlagsPager = document.getElementById('featureFlagsPager');
    var flagSearchInput = document.getElementById('flagSearchInput');

    var overrideForm = document.getElementById('overrideForm');
    var overrideFlagInput = document.getElementById('overrideFlagInput');
    var overrideScopeInput = document.getElementById('overrideScopeInput');
    var overrideCountryInput = document.getElementById('overrideCountryInput');
    var overrideTenantInput = document.getElementById('overrideTenantInput');
    var overrideValueInput = document.getElementById('overrideValueInput');
    var overridesList = document.getElementById('overridesList');
    var overridesPager = document.getElementById('overridesPager');
    var overrideSearchInput = document.getElementById('overrideSearchInput');
    var overrideFilterScopeInput = document.getElementById('overrideFilterScopeInput');
    var overrideFormResetBtn = document.getElementById('overrideFormResetBtn');
    var bulkFlagInput = document.getElementById('bulkFlagInput');
    var bulkScopeInput = document.getElementById('bulkScopeInput');
    var bulkCountryInput = document.getElementById('bulkCountryInput');
    var bulkTenantInput = document.getElementById('bulkTenantInput');
    var bulkEnableOverridesBtn = document.getElementById('bulkEnableOverridesBtn');
    var bulkDisableOverridesBtn = document.getElementById('bulkDisableOverridesBtn');
    var resolverForm = document.getElementById('resolverForm');
    var resolverFlagInput = document.getElementById('resolverFlagInput');
    var resolverCountryInput = document.getElementById('resolverCountryInput');
    var resolverTenantInput = document.getElementById('resolverTenantInput');
    var resolverResult = document.getElementById('resolverResult');

    var state = {
        countries: [],
        tenants: [],
        flags: [],
        overrides: [],
        pagination: {
            tenants: 1,
            flags: 1,
            overrides: 1
        }
    };
    var pageSize = 5;

    function showFlash(text, ok) {
        if (!flash) return;
        flash.classList.remove('d-none', 'is-ok', 'is-fail');
        flash.classList.add(ok ? 'is-ok' : 'is-fail');
        flash.textContent = text;
        window.setTimeout(function () {
            flash.classList.add('d-none');
        }, 1800);
    }

    function esc(value) {
        var d = document.createElement('div');
        d.textContent = value == null ? '' : String(value);
        return d.innerHTML;
    }

    function request(method, payload) {
        return fetch(apiBase + '/tenant-rollout.php', {
            method: method,
            credentials: 'same-origin',
            headers: method === 'POST' ? { 'Content-Type': 'application/json' } : undefined,
            body: method === 'POST' ? JSON.stringify(payload || {}) : undefined
        }).then(function (res) {
            return res.text().then(function (text) {
                var data = null;
                try {
                    data = JSON.parse(text);
                } catch (_) {
                    data = null;
                }
                if (!res.ok) {
                    var serverMessage = (data && data.message) ? data.message : (text || ('HTTP ' + res.status));
                    throw new Error(serverMessage);
                }
                if (!data) {
                    throw new Error('Invalid JSON response from server.');
                }
                return data;
            });
        });
    }

    function refreshSelectOptions() {
        if (tenantCountryInput) {
            tenantCountryInput.innerHTML = '<option value="">Select country</option>' + state.countries.map(function (c) {
                return '<option value="' + Number(c.id || 0) + '">' + esc(c.name || c.slug || '') + '</option>';
            }).join('');
        }

        if (overrideCountryInput) {
            overrideCountryInput.innerHTML = '<option value="">Select country</option>' + state.countries.map(function (c) {
                return '<option value="' + Number(c.id || 0) + '">' + esc(c.name || c.slug || '') + '</option>';
            }).join('');
        }

        if (overrideTenantInput) {
            overrideTenantInput.innerHTML = '<option value="">Select tenant</option>' + state.tenants.map(function (t) {
                return '<option value="' + Number(t.id || 0) + '">' + esc(t.tenant_name || t.tenant_code || '') + '</option>';
            }).join('');
        }

        if (overrideFlagInput) {
            overrideFlagInput.innerHTML = '<option value="">Select flag</option>' + state.flags.map(function (f) {
                return '<option value="' + Number(f.id || 0) + '">' + esc(f.flag_key || '') + '</option>';
            }).join('');
        }
        if (resolverFlagInput) {
            resolverFlagInput.innerHTML = '<option value="">Select flag</option>' + state.flags.map(function (f) {
                return '<option value="' + esc(f.flag_key || '') + '">' + esc(f.flag_key || '') + '</option>';
            }).join('');
        }
        if (bulkFlagInput) {
            bulkFlagInput.innerHTML = '<option value="">Select flag</option>' + state.flags.map(function (f) {
                return '<option value="' + Number(f.id || 0) + '">' + esc(f.flag_key || '') + '</option>';
            }).join('');
        }
        if (bulkCountryInput) {
            bulkCountryInput.innerHTML = '<option value="">Select country</option>' + state.countries.map(function (c) {
                return '<option value="' + Number(c.id || 0) + '">' + esc(c.name || c.slug || '') + '</option>';
            }).join('');
        }
        if (resolverCountryInput) {
            resolverCountryInput.innerHTML = '<option value="">Optional country</option>' + state.countries.map(function (c) {
                return '<option value="' + Number(c.id || 0) + '">' + esc(c.name || c.slug || '') + '</option>';
            }).join('');
        }
        if (bulkTenantInput) {
            bulkTenantInput.innerHTML = '<option value="">Select tenant</option>' + state.tenants.map(function (t) {
                return '<option value="' + Number(t.id || 0) + '">' + esc(t.tenant_name || t.tenant_code || '') + '</option>';
            }).join('');
        }
        if (resolverTenantInput) {
            resolverTenantInput.innerHTML = '<option value="">Optional tenant</option>' + state.tenants.map(function (t) {
                return '<option value="' + Number(t.id || 0) + '">' + esc(t.tenant_name || t.tenant_code || '') + '</option>';
            }).join('');
        }
        syncScopeFields();
        syncBulkScopeFields();
    }

    function paginate(items, page) {
        var total = items.length;
        var pages = Math.max(1, Math.ceil(total / pageSize));
        var safePage = Math.min(Math.max(1, page || 1), pages);
        var start = (safePage - 1) * pageSize;
        return {
            items: items.slice(start, start + pageSize),
            page: safePage,
            pages: pages
        };
    }

    function renderPager(el, key, page, pages) {
        if (!el) return;
        if (pages <= 1) {
            el.innerHTML = '';
            return;
        }
        var html = '';
        html += '<button type="button" class="btn btn-sm btn-outline-light js-page-btn" data-key="' + key + '" data-page="' + (page - 1) + '"' + (page <= 1 ? ' disabled' : '') + '>Prev</button>';
        html += '<span class="btn btn-sm btn-outline-secondary disabled">Page ' + page + ' / ' + pages + '</span>';
        html += '<button type="button" class="btn btn-sm btn-outline-light js-page-btn" data-key="' + key + '" data-page="' + (page + 1) + '"' + (page >= pages ? ' disabled' : '') + '>Next</button>';
        el.innerHTML = html;
    }

    function renderTenants() {
        if (!tenantRegistryList) return;
        var q = ((tenantSearchInput && tenantSearchInput.value) || '').trim().toLowerCase();
        var filtered = state.tenants.filter(function (t) {
            if (!q) return true;
            var hay = [t.tenant_name, t.tenant_code, t.primary_domain, t.country_name, t.status].join(' ').toLowerCase();
            return hay.indexOf(q) !== -1;
        });
        if (!filtered.length) {
            tenantRegistryList.innerHTML = '<div class="tenant-rollout-empty">No tenants yet.</div>';
            renderPager(tenantRegistryPager, 'tenants', 1, 1);
            return;
        }
        var p = paginate(filtered, state.pagination.tenants);
        state.pagination.tenants = p.page;
        tenantRegistryList.innerHTML = p.items.map(function (t) {
            var country = t.country_name || 'N/A';
            return '<div class="tenant-rollout-item">' +
                '<p class="tenant-rollout-item-title">' + esc(t.tenant_name || '') + '</p>' +
                '<p class="tenant-rollout-item-meta">' + esc(t.tenant_code || '') + ' | ' + esc(t.primary_domain || '') + '</p>' +
                '<p class="tenant-rollout-item-meta">Country: ' + esc(country) + ' | Status: ' + esc(t.status || '') + '</p>' +
                '<div class="tenant-rollout-item-actions">' +
                '<button type="button" class="btn btn-sm btn-outline-light js-edit-tenant" data-id="' + Number(t.id || 0) + '">Edit</button>' +
                '</div>' +
                '</div>';
        }).join('');
        renderPager(tenantRegistryPager, 'tenants', p.page, p.pages);
    }

    function renderFlags() {
        if (!featureFlagsList) return;
        var q = ((flagSearchInput && flagSearchInput.value) || '').trim().toLowerCase();
        var filtered = state.flags.filter(function (f) {
            if (!q) return true;
            var hay = [f.flag_key, f.flag_description].join(' ').toLowerCase();
            return hay.indexOf(q) !== -1;
        });
        if (!filtered.length) {
            featureFlagsList.innerHTML = '<div class="tenant-rollout-empty">No feature flags yet.</div>';
            renderPager(featureFlagsPager, 'flags', 1, 1);
            return;
        }
        var p = paginate(filtered, state.pagination.flags);
        state.pagination.flags = p.page;
        featureFlagsList.innerHTML = p.items.map(function (f) {
            return '<div class="tenant-rollout-item">' +
                '<p class="tenant-rollout-item-title">' + esc(f.flag_key || '') + '</p>' +
                '<p class="tenant-rollout-item-meta">' + esc(f.flag_description || 'No description') + '</p>' +
                '<p class="tenant-rollout-item-meta">Default: ' + (Number(f.default_value || 0) > 0 ? 'Enabled' : 'Disabled') + '</p>' +
                '<div class="tenant-rollout-item-actions">' +
                '<button type="button" class="btn btn-sm btn-outline-light js-edit-flag" data-id="' + Number(f.id || 0) + '">Edit</button>' +
                '</div>' +
                '</div>';
        }).join('');
        renderPager(featureFlagsPager, 'flags', p.page, p.pages);
    }

    function renderOverrides() {
        if (!overridesList) return;
        var q = ((overrideSearchInput && overrideSearchInput.value) || '').trim().toLowerCase();
        var scopeFilter = ((overrideFilterScopeInput && overrideFilterScopeInput.value) || '').trim().toLowerCase();
        var filtered = state.overrides.filter(function (o) {
            if (scopeFilter && String(o.scope_type || '').toLowerCase() !== scopeFilter) return false;
            if (!q) return true;
            var hay = [o.flag_key, o.scope_type, o.country_name, o.tenant_name].join(' ').toLowerCase();
            return hay.indexOf(q) !== -1;
        });
        if (!filtered.length) {
            overridesList.innerHTML = '<div class="tenant-rollout-empty">No overrides yet.</div>';
            renderPager(overridesPager, 'overrides', 1, 1);
            return;
        }
        var p = paginate(filtered, state.pagination.overrides);
        state.pagination.overrides = p.page;
        overridesList.innerHTML = p.items.map(function (o) {
            var target = o.scope_type === 'country' ? (o.country_name || 'Unknown country') : (o.tenant_name || 'Unknown tenant');
            return '<div class="tenant-rollout-item">' +
                '<p class="tenant-rollout-item-title">' + esc(o.flag_key || '') + '</p>' +
                '<p class="tenant-rollout-item-meta">' + esc(o.scope_type || '') + ': ' + esc(target) + '</p>' +
                '<p class="tenant-rollout-item-meta">Value: ' + (Number(o.override_value || 0) > 0 ? 'Enabled' : 'Disabled') + '</p>' +
                '<div class="tenant-rollout-item-actions">' +
                '<button type="button" class="btn btn-sm btn-outline-light js-edit-override" data-id="' + Number(o.id || 0) + '">Edit</button>' +
                '<button type="button" class="btn btn-sm btn-outline-danger js-delete-override" data-id="' + Number(o.id || 0) + '">Delete</button>' +
                '</div>' +
                '</div>';
        }).join('');
        renderPager(overridesPager, 'overrides', p.page, p.pages);
    }

    function hydrate(payload) {
        state.countries = Array.isArray(payload.countries) ? payload.countries : [];
        state.tenants = Array.isArray(payload.tenants) ? payload.tenants : [];
        state.flags = Array.isArray(payload.flags) ? payload.flags : [];
        state.overrides = Array.isArray(payload.overrides) ? payload.overrides : [];
        refreshSelectOptions();
        renderTenants();
        renderFlags();
        renderOverrides();
    }

    function loadAll() {
        request('GET').then(function (res) {
            if (!res || !res.success) {
                showFlash((res && res.message) || 'Failed to load rollout data.', false);
                return;
            }
            hydrate(res);
        }).catch(function (err) {
            var msg = err && err.message ? err.message : 'Failed to load rollout data.';
            try { console.error('tenant-rollout load error:', err); } catch (_) {}
            showFlash(msg, false);
        });
    }

    function resetTenantForm() {
        if (!tenantForm) return;
        tenantForm.reset();
        tenantIdInput.value = '';
    }

    function resetFlagForm() {
        if (!flagForm) return;
        flagForm.reset();
        flagIdInput.value = '';
    }

    function resetOverrideForm() {
        if (!overrideForm) return;
        overrideForm.reset();
        syncScopeFields();
    }

    function syncScopeFields() {
        var scope = overrideScopeInput ? overrideScopeInput.value : 'country';
        if (overrideCountryInput) {
            overrideCountryInput.disabled = scope !== 'country';
        }
        if (overrideTenantInput) {
            overrideTenantInput.disabled = scope !== 'tenant';
        }
    }

    function syncBulkScopeFields() {
        var scope = bulkScopeInput ? bulkScopeInput.value : 'country';
        if (bulkCountryInput) {
            bulkCountryInput.disabled = scope !== 'country';
        }
        if (bulkTenantInput) {
            bulkTenantInput.disabled = scope !== 'tenant';
        }
    }

    if (copyBtn && urlEl) {
        copyBtn.addEventListener('click', function () {
            var url = urlEl.textContent ? urlEl.textContent.trim() : '';
            if (!url) {
                showFlash('No link found.', false);
                return;
            }
            if (!navigator.clipboard || !navigator.clipboard.writeText) {
                showFlash('Clipboard is not supported in this browser.', false);
                return;
            }
            navigator.clipboard.writeText(url)
                .then(function () {
                    showFlash('Link copied to clipboard.', true);
                })
                .catch(function () {
                    showFlash('Failed to copy link.', false);
                });
        });
    }

    if (tenantForm) {
        tenantForm.addEventListener('submit', function (event) {
            event.preventDefault();
            request('POST', {
                action: 'save_tenant',
                id: Number(tenantIdInput.value || 0),
                tenant_code: (tenantCodeInput.value || '').trim(),
                tenant_name: (tenantNameInput.value || '').trim(),
                primary_domain: (tenantDomainInput.value || '').trim(),
                country_id: Number(tenantCountryInput.value || 0),
                db_key_ref: (tenantDbKeyInput.value || '').trim(),
                status: tenantStatusInput.value || 'active'
            }).then(function (res) {
                if (!res || !res.success) {
                    showFlash((res && res.message) || 'Failed to save tenant.', false);
                    return;
                }
                showFlash(res.message || 'Tenant saved.', true);
                resetTenantForm();
                loadAll();
            }).catch(function (err) {
                var msg = err && err.message ? err.message : 'Failed to save tenant.';
                try { console.error('tenant-rollout save tenant error:', err); } catch (_) {}
                showFlash(msg, false);
            });
        });
    }

    if (flagForm) {
        flagForm.addEventListener('submit', function (event) {
            event.preventDefault();
            request('POST', {
                action: 'save_flag',
                id: Number(flagIdInput.value || 0),
                flag_key: (flagKeyInput.value || '').trim(),
                flag_description: (flagDescriptionInput.value || '').trim(),
                default_value: Number(flagDefaultInput.value || 0)
            }).then(function (res) {
                if (!res || !res.success) {
                    showFlash((res && res.message) || 'Failed to save flag.', false);
                    return;
                }
                showFlash(res.message || 'Flag saved.', true);
                resetFlagForm();
                loadAll();
            }).catch(function (err) {
                var msg = err && err.message ? err.message : 'Failed to save flag.';
                try { console.error('tenant-rollout save flag error:', err); } catch (_) {}
                showFlash(msg, false);
            });
        });
    }

    if (overrideForm) {
        overrideForm.addEventListener('submit', function (event) {
            event.preventDefault();
            var scope = overrideScopeInput.value || 'country';
            var flagId = Number(overrideFlagInput.value || 0);
            var countryId = Number(overrideCountryInput.value || 0);
            var tenantId = Number(overrideTenantInput.value || 0);
            if (flagId <= 0) {
                showFlash('Please select a flag first.', false);
                return;
            }
            if (scope === 'country' && countryId <= 0) {
                showFlash('Please select a country for country override.', false);
                return;
            }
            if (scope === 'tenant' && tenantId <= 0) {
                showFlash('Please select a tenant for tenant override.', false);
                return;
            }
            request('POST', {
                action: 'save_override',
                flag_id: flagId,
                scope_type: scope,
                country_id: countryId,
                tenant_id: tenantId,
                override_value: Number(overrideValueInput.value || 0)
            }).then(function (res) {
                if (!res || !res.success) {
                    showFlash((res && res.message) || 'Failed to save override.', false);
                    return;
                }
                showFlash(res.message || 'Override saved.', true);
                overrideForm.reset();
                syncScopeFields();
                loadAll();
            }).catch(function (err) {
                var msg = err && err.message ? err.message : 'Failed to save override.';
                try { console.error('tenant-rollout save override error:', err); } catch (_) {}
                showFlash(msg, false);
            });
        });
    }

    if (tenantFormResetBtn) {
        tenantFormResetBtn.addEventListener('click', resetTenantForm);
    }

    if (flagFormResetBtn) {
        flagFormResetBtn.addEventListener('click', resetFlagForm);
    }
    if (overrideFormResetBtn) {
        overrideFormResetBtn.addEventListener('click', resetOverrideForm);
    }

    function onFilterChanged(key) {
        state.pagination[key] = 1;
        if (key === 'tenants') renderTenants();
        if (key === 'flags') renderFlags();
        if (key === 'overrides') renderOverrides();
    }
    if (tenantSearchInput) tenantSearchInput.addEventListener('input', function () { onFilterChanged('tenants'); });
    if (flagSearchInput) flagSearchInput.addEventListener('input', function () { onFilterChanged('flags'); });
    if (overrideSearchInput) overrideSearchInput.addEventListener('input', function () { onFilterChanged('overrides'); });
    if (overrideFilterScopeInput) overrideFilterScopeInput.addEventListener('change', function () { onFilterChanged('overrides'); });

    if (tenantRegistryList) {
        tenantRegistryList.addEventListener('click', function (event) {
            var btn = event.target.closest('.js-edit-tenant');
            if (!btn) return;
            var id = Number(btn.getAttribute('data-id') || 0);
            var tenant = state.tenants.find(function (item) { return Number(item.id || 0) === id; });
            if (!tenant) return;
            tenantIdInput.value = String(tenant.id || '');
            tenantCodeInput.value = tenant.tenant_code || '';
            tenantNameInput.value = tenant.tenant_name || '';
            tenantDomainInput.value = tenant.primary_domain || '';
            tenantCountryInput.value = String(tenant.country_id || '');
            tenantDbKeyInput.value = tenant.db_key_ref || '';
            tenantStatusInput.value = tenant.status || 'active';
            showFlash('Tenant loaded into form.', true);
        });
    }

    if (featureFlagsList) {
        featureFlagsList.addEventListener('click', function (event) {
            var btn = event.target.closest('.js-edit-flag');
            if (!btn) return;
            var id = Number(btn.getAttribute('data-id') || 0);
            var flag = state.flags.find(function (item) { return Number(item.id || 0) === id; });
            if (!flag) return;
            flagIdInput.value = String(flag.id || '');
            flagKeyInput.value = flag.flag_key || '';
            flagDescriptionInput.value = flag.flag_description || '';
            flagDefaultInput.value = Number(flag.default_value || 0) > 0 ? '1' : '0';
            showFlash('Flag loaded into form.', true);
        });
    }

    if (overridesList) {
        overridesList.addEventListener('click', function (event) {
            var editBtn = event.target.closest('.js-edit-override');
            if (editBtn) {
                var editId = Number(editBtn.getAttribute('data-id') || 0);
                var ov = state.overrides.find(function (item) { return Number(item.id || 0) === editId; });
                if (ov) {
                    overrideFlagInput.value = String(ov.flag_id || '');
                    overrideScopeInput.value = ov.scope_type || 'country';
                    overrideCountryInput.value = String(ov.country_id || '');
                    overrideTenantInput.value = String(ov.tenant_id || '');
                    overrideValueInput.value = Number(ov.override_value || 0) > 0 ? '1' : '0';
                    syncScopeFields();
                    showFlash('Override loaded into form.', true);
                }
                return;
            }
            var btn = event.target.closest('.js-delete-override');
            if (!btn) return;
            var id = Number(btn.getAttribute('data-id') || 0);
            if (!id) return;
            var confirmDelete = window.confirm('Delete this override? This cannot be undone.');
            if (!confirmDelete) return;
            request('POST', { action: 'delete_override', id: id }).then(function (res) {
                if (!res || !res.success) {
                    showFlash((res && res.message) || 'Failed to remove override.', false);
                    return;
                }
                showFlash(res.message || 'Override removed.', true);
                loadAll();
            }).catch(function (err) {
                var msg = err && err.message ? err.message : 'Failed to remove override.';
                try { console.error('tenant-rollout delete override error:', err); } catch (_) {}
                showFlash(msg, false);
            });
        });
    }

    function bindPagerClicks(el) {
        if (!el) return;
        el.addEventListener('click', function (event) {
            var btn = event.target.closest('.js-page-btn');
            if (!btn) return;
            var key = btn.getAttribute('data-key');
            var page = Number(btn.getAttribute('data-page') || 1);
            if (!key || !state.pagination[key]) return;
            state.pagination[key] = Math.max(1, page);
            if (key === 'tenants') renderTenants();
            if (key === 'flags') renderFlags();
            if (key === 'overrides') renderOverrides();
        });
    }
    bindPagerClicks(tenantRegistryPager);
    bindPagerClicks(featureFlagsPager);
    bindPagerClicks(overridesPager);

    if (overrideScopeInput) {
        overrideScopeInput.addEventListener('change', syncScopeFields);
    }
    if (bulkScopeInput) {
        bulkScopeInput.addEventListener('change', syncBulkScopeFields);
    }

    if (bulkDisableOverridesBtn) {
        var runBulkOverrideAction = function (actionName, okText) {
            var flagId = Number((bulkFlagInput && bulkFlagInput.value) || 0);
            var scope = (bulkScopeInput && bulkScopeInput.value) || 'country';
            var countryId = Number((bulkCountryInput && bulkCountryInput.value) || 0);
            var tenantId = Number((bulkTenantInput && bulkTenantInput.value) || 0);
            if (flagId <= 0) {
                showFlash('Select a flag for bulk action.', false);
                return;
            }
            if (scope === 'country' && countryId <= 0) {
                showFlash('Select a country for bulk country action.', false);
                return;
            }
            if (scope === 'tenant' && tenantId <= 0) {
                showFlash('Select a tenant for bulk tenant action.', false);
                return;
            }
            request('POST', {
                action: actionName,
                flag_id: flagId,
                scope_type: scope,
                country_id: countryId,
                tenant_id: tenantId
            }).then(function (res) {
                if (!res || !res.success) {
                    showFlash((res && res.message) || 'Bulk action failed.', false);
                    return;
                }
                showFlash(res.message || okText, true);
                loadAll();
            }).catch(function (err) {
                showFlash((err && err.message) || 'Bulk action failed.', false);
            });
        };

        bulkDisableOverridesBtn.addEventListener('click', function () {
            var confirmDisable = window.confirm('Disable all matching overrides for this selection?');
            if (!confirmDisable) return;
            runBulkOverrideAction('bulk_disable_overrides', 'Bulk disable completed.');
        });
        if (bulkEnableOverridesBtn) {
            bulkEnableOverridesBtn.addEventListener('click', function () {
                runBulkOverrideAction('bulk_enable_overrides', 'Bulk enable completed.');
            });
        }
    }

    if (resolverForm) {
        resolverForm.addEventListener('submit', function (event) {
            event.preventDefault();
            var flagKey = (resolverFlagInput && resolverFlagInput.value) ? String(resolverFlagInput.value).trim() : '';
            var countryId = Number((resolverCountryInput && resolverCountryInput.value) || 0);
            var tenantId = Number((resolverTenantInput && resolverTenantInput.value) || 0);
            if (!flagKey) {
                showFlash('Select a flag first.', false);
                return;
            }
            var url = apiBase + '/tenant-rollout-resolver.php?flag_key=' + encodeURIComponent(flagKey)
                + '&country_id=' + encodeURIComponent(String(countryId))
                + '&tenant_id=' + encodeURIComponent(String(tenantId));
            fetch(url, { credentials: 'same-origin', cache: 'no-store' })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (!data || !data.success || !data.resolved) {
                        throw new Error((data && data.message) || 'Resolver failed');
                    }
                    var r = data.resolved || {};
                    if (resolverResult) {
                        resolverResult.className = 'tenant-rollout-item';
                        resolverResult.innerHTML =
                            '<p class="tenant-rollout-item-title">' + esc(r.flag_key || flagKey) + '</p>' +
                            '<p class="tenant-rollout-item-meta">Effective value: ' + (Number(r.value || 0) > 0 ? 'Enabled' : 'Disabled') + '</p>' +
                            '<p class="tenant-rollout-item-meta">Source: ' + esc(r.source || 'unknown') + '</p>' +
                            '<p class="tenant-rollout-item-meta">Tenant ID: ' + esc(String(r.tenant_id || 0)) + ' | Country ID: ' + esc(String(r.country_id || 0)) + '</p>';
                    }
                })
                .catch(function (err) {
                    var msg = (err && err.message) ? err.message : 'Resolver failed';
                    if (resolverResult) {
                        resolverResult.className = 'tenant-rollout-empty';
                        resolverResult.textContent = msg;
                    }
                    showFlash(msg, false);
                });
        });
    }

    syncScopeFields();
    syncBulkScopeFields();
    loadAll();
})();
