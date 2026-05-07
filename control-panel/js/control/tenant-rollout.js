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

    var flagForm = document.getElementById('flagForm');
    var flagIdInput = document.getElementById('flagIdInput');
    var flagKeyInput = document.getElementById('flagKeyInput');
    var flagDescriptionInput = document.getElementById('flagDescriptionInput');
    var flagDefaultInput = document.getElementById('flagDefaultInput');
    var flagFormResetBtn = document.getElementById('flagFormResetBtn');
    var featureFlagsList = document.getElementById('featureFlagsList');

    var overrideForm = document.getElementById('overrideForm');
    var overrideFlagInput = document.getElementById('overrideFlagInput');
    var overrideScopeInput = document.getElementById('overrideScopeInput');
    var overrideCountryInput = document.getElementById('overrideCountryInput');
    var overrideTenantInput = document.getElementById('overrideTenantInput');
    var overrideValueInput = document.getElementById('overrideValueInput');
    var overridesList = document.getElementById('overridesList');

    var state = {
        countries: [],
        tenants: [],
        flags: [],
        overrides: []
    };

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
        syncScopeFields();
    }

    function renderTenants() {
        if (!tenantRegistryList) return;
        if (!state.tenants.length) {
            tenantRegistryList.innerHTML = '<div class="tenant-rollout-empty">No tenants yet.</div>';
            return;
        }
        tenantRegistryList.innerHTML = state.tenants.map(function (t) {
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
    }

    function renderFlags() {
        if (!featureFlagsList) return;
        if (!state.flags.length) {
            featureFlagsList.innerHTML = '<div class="tenant-rollout-empty">No feature flags yet.</div>';
            return;
        }
        featureFlagsList.innerHTML = state.flags.map(function (f) {
            return '<div class="tenant-rollout-item">' +
                '<p class="tenant-rollout-item-title">' + esc(f.flag_key || '') + '</p>' +
                '<p class="tenant-rollout-item-meta">' + esc(f.flag_description || 'No description') + '</p>' +
                '<p class="tenant-rollout-item-meta">Default: ' + (Number(f.default_value || 0) > 0 ? 'Enabled' : 'Disabled') + '</p>' +
                '<div class="tenant-rollout-item-actions">' +
                '<button type="button" class="btn btn-sm btn-outline-light js-edit-flag" data-id="' + Number(f.id || 0) + '">Edit</button>' +
                '</div>' +
                '</div>';
        }).join('');
    }

    function renderOverrides() {
        if (!overridesList) return;
        if (!state.overrides.length) {
            overridesList.innerHTML = '<div class="tenant-rollout-empty">No overrides yet.</div>';
            return;
        }
        overridesList.innerHTML = state.overrides.map(function (o) {
            var target = o.scope_type === 'country' ? (o.country_name || 'Unknown country') : (o.tenant_name || 'Unknown tenant');
            return '<div class="tenant-rollout-item">' +
                '<p class="tenant-rollout-item-title">' + esc(o.flag_key || '') + '</p>' +
                '<p class="tenant-rollout-item-meta">' + esc(o.scope_type || '') + ': ' + esc(target) + '</p>' +
                '<p class="tenant-rollout-item-meta">Value: ' + (Number(o.override_value || 0) > 0 ? 'Enabled' : 'Disabled') + '</p>' +
                '<div class="tenant-rollout-item-actions">' +
                '<button type="button" class="btn btn-sm btn-outline-danger js-delete-override" data-id="' + Number(o.id || 0) + '">Delete</button>' +
                '</div>' +
                '</div>';
        }).join('');
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

    function syncScopeFields() {
        var scope = overrideScopeInput ? overrideScopeInput.value : 'country';
        if (overrideCountryInput) {
            overrideCountryInput.disabled = scope !== 'country';
        }
        if (overrideTenantInput) {
            overrideTenantInput.disabled = scope !== 'tenant';
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
            var btn = event.target.closest('.js-delete-override');
            if (!btn) return;
            var id = Number(btn.getAttribute('data-id') || 0);
            if (!id) return;
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

    if (overrideScopeInput) {
        overrideScopeInput.addEventListener('change', syncScopeFields);
    }

    syncScopeFields();
    loadAll();
})();
