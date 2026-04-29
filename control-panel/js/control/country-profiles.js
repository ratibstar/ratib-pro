(function () {
    var root = document.getElementById('country-profiles-page');
    if (!root) return;
    var cfg = document.getElementById('control-config');
    var apiBase = (cfg && cfg.getAttribute('data-api-base')) || '';
    apiBase = apiBase.replace(/\/$/, '');
    if (!apiBase) return;

    var wrap = document.getElementById('countryProfilesWrap');
    var flashEl = document.getElementById('countryProfilesFlash');
    var exportBtn = document.getElementById('countryProfilesExportBtn');
    var importBtn = document.getElementById('countryProfilesImportBtn');
    var importInput = document.getElementById('countryProfilesImportInput');
    var availableRequirementFields = [
        'full_name', 'gender', 'agent_id',
        'identity_number', 'passport_number', 'police_number', 'medical_number', 'visa_number', 'ticket_number',
        'training_certificate_number', 'contract_signed_number', 'insurance_number', 'exit_permit_number', 'approval_reference_id',
        'government_registration_number', 'work_permit_number', 'insurance_policy_number',
        'salary', 'contract_duration', 'flight_ticket_number', 'predeparture_training_completed', 'contract_verified'
    ];
    var defaults = {
        indonesia: {
            labels: { government: 'Government Approval', workPermit: 'Exit Permit', contract: 'Signed Contract', travel: 'Travel Readiness' },
            requirements: ['full_name', 'gender', 'agent_id', 'identity_number', 'passport_number', 'police_number', 'medical_number', 'visa_number', 'ticket_number', 'training_certificate_number', 'contract_signed_number', 'insurance_number', 'exit_permit_number', 'approval_reference_id']
        },
        bangladesh: {
            labels: { government: 'BMET Registration', workPermit: 'Work Permit', contract: 'Overseas Contract', travel: 'Travel Clearance' },
            requirements: ['full_name', 'gender', 'agent_id', 'identity_number', 'passport_number', 'police_number', 'medical_number', 'visa_number', 'ticket_number', 'government_registration_number', 'work_permit_number', 'insurance_policy_number', 'salary', 'contract_duration', 'flight_ticket_number', 'predeparture_training_completed', 'contract_verified']
        },
        sri_lanka: {
            labels: { government: 'SLBFE Registration', workPermit: 'Work Permit', contract: 'Employment Contract', travel: 'Departure Clearance' },
            requirements: ['full_name', 'gender', 'agent_id', 'identity_number', 'passport_number', 'police_number', 'medical_number', 'visa_number', 'ticket_number', 'government_registration_number', 'work_permit_number', 'insurance_policy_number', 'salary', 'contract_duration', 'flight_ticket_number', 'predeparture_training_completed', 'contract_verified']
        },
        kenya: {
            labels: { government: 'NITA Registration', workPermit: 'Work Permit', contract: 'Employment Contract', travel: 'Travel Clearance' },
            requirements: ['full_name', 'gender', 'agent_id', 'identity_number', 'passport_number', 'police_number', 'medical_number', 'visa_number', 'ticket_number', 'government_registration_number', 'work_permit_number', 'insurance_policy_number', 'salary', 'contract_duration', 'flight_ticket_number', 'predeparture_training_completed', 'contract_verified']
        },
        default: {
            labels: { government: 'Government Registration', workPermit: 'Work Permit', contract: 'Contract', travel: 'Travel & Departure' },
            requirements: ['full_name', 'gender', 'agent_id', 'identity_number', 'passport_number', 'police_number', 'medical_number', 'visa_number', 'ticket_number', 'government_registration_number', 'work_permit_number']
        }
    };

    function flash(msg, ok) {
        if (!flashEl) return;
        flashEl.textContent = msg;
        flashEl.className = 'alert mt-3 ' + (ok ? 'alert-success' : 'alert-danger');
        flashEl.classList.remove('d-none');
        setTimeout(function () { flashEl.classList.add('d-none'); }, 3500);
    }
    function apiGet(action) {
        var url = apiBase + '/country-profiles.php';
        if (action) url += '?action=' + encodeURIComponent(action);
        return fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); });
    }
    function apiSave(body) {
        return fetch(apiBase + '/country-profiles.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        }).then(function (r) { return r.json(); });
    }
    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }
    function buildPreview(effective) {
        var labels = (effective && effective.labels) || {};
        var req = (effective && effective.requirements) || [];
        return '<div class="col-12 mt-2">' +
            '<div class="border rounded p-2 bg-dark bg-opacity-10">' +
            '<div class="small text-muted mb-1">Effective preview (read-only, exactly worker form behavior)</div>' +
            '<div class="small"><strong>Labels:</strong> government=' + esc(labels.government || '') + ', workPermit=' + esc(labels.workPermit || '') + ', contract=' + esc(labels.contract || '') + ', travel=' + esc(labels.travel || '') + '</div>' +
            '<div class="small mt-1"><strong>Requirements:</strong> ' + esc(req.join(', ')) + '</div>' +
            '</div></div>';
    }

    function buildCard(slug, cfgData, effective) {
        var chips = availableRequirementFields.map(function (f) {
            return '<button type="button" class="btn btn-outline-secondary btn-sm cp-chip me-1 mb-1" data-slug="' + esc(slug) + '" data-field="' + esc(f) + '">' + esc(f) + '</button>';
        }).join('');
        return '<div class="card gov-card"><div class="card-body">' +
            '<div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">' +
                '<h6 class="mb-0 text-uppercase">' + esc(slug) + '</h6>' +
                '<div>' +
                    '<button class="btn btn-sm btn-outline-light cp-load-default me-1" data-slug="' + esc(slug) + '">Import Defaults</button>' +
                    '<button class="btn btn-sm btn-primary cp-save" data-slug="' + esc(slug) + '">Save</button>' +
                '</div>' +
            '</div>' +
            '<div class="row g-2">' +
            '<div class="col-md-3"><input class="form-control form-control-sm cp-label" data-slug="' + esc(slug) + '" data-key="government" value="' + esc((cfgData.labels || {}).government || '') + '" placeholder="Government label"></div>' +
            '<div class="col-md-3"><input class="form-control form-control-sm cp-label" data-slug="' + esc(slug) + '" data-key="workPermit" value="' + esc((cfgData.labels || {}).workPermit || '') + '" placeholder="Work Permit label"></div>' +
            '<div class="col-md-3"><input class="form-control form-control-sm cp-label" data-slug="' + esc(slug) + '" data-key="contract" value="' + esc((cfgData.labels || {}).contract || '') + '" placeholder="Contract label"></div>' +
            '<div class="col-md-3"><input class="form-control form-control-sm cp-label" data-slug="' + esc(slug) + '" data-key="travel" value="' + esc((cfgData.labels || {}).travel || '') + '" placeholder="Travel label"></div>' +
            '<div class="col-12"><textarea class="form-control form-control-sm cp-req" data-slug="' + esc(slug) + '" rows="2" placeholder="Comma-separated required fields">' + esc((cfgData.requirements || []).join(', ')) + '</textarea></div>' +
            '<div class="col-12"><div class="small text-muted mb-1">Requirement field picker:</div>' + chips + '</div>' +
            buildPreview(effective) +
            '</div></div></div>';
    }

    function render(map, previewMap) {
        var html = '';
        Object.keys(defaults).forEach(function (slug) {
            html += buildCard(slug, map[slug] || defaults[slug], (previewMap && previewMap[slug]) || map[slug] || defaults[slug]);
        });
        wrap.innerHTML = html;
    }

    wrap.addEventListener('click', function (e) {
        var importBtn = e.target.closest('.cp-load-default');
        if (importBtn) {
            var importSlug = importBtn.getAttribute('data-slug');
            var d = defaults[importSlug];
            if (!d) return;
            wrap.querySelectorAll('.cp-label[data-slug="' + importSlug + '"]').forEach(function (i) {
                var k = i.getAttribute('data-key');
                i.value = ((d.labels || {})[k] || '');
            });
            var reqInput = wrap.querySelector('.cp-req[data-slug="' + importSlug + '"]');
            if (reqInput) reqInput.value = (d.requirements || []).join(', ');
            flash('Defaults loaded for ' + importSlug + '. Click Save to apply.', true);
            return;
        }
        var chip = e.target.closest('.cp-chip');
        if (chip) {
            var chipSlug = chip.getAttribute('data-slug');
            var field = chip.getAttribute('data-field');
            var reqEl = wrap.querySelector('.cp-req[data-slug="' + chipSlug + '"]');
            if (!reqEl || !field) return;
            var parts = reqEl.value.split(',').map(function (x) { return x.trim(); }).filter(Boolean);
            if (!parts.includes(field)) {
                parts.push(field);
                reqEl.value = parts.join(', ');
            }
            return;
        }
        var b = e.target.closest('.cp-save');
        if (!b) return;
        var slug = b.getAttribute('data-slug');
        var labels = {};
        wrap.querySelectorAll('.cp-label[data-slug="' + slug + '"]').forEach(function (i) {
            labels[i.getAttribute('data-key')] = (i.value || '').trim();
        });
        var reqRaw = wrap.querySelector('.cp-req[data-slug="' + slug + '"]');
        var requirements = (reqRaw && reqRaw.value ? reqRaw.value : '')
            .split(',')
            .map(function (x) { return x.trim(); })
            .filter(Boolean);
        b.disabled = true;
        apiSave({ country_slug: slug, labels: labels, requirements: requirements })
            .then(function (res) {
                if (!res.success) throw new Error(res.message || 'Save failed');
                flash('Saved ' + slug + ' profile', true);
                loadAll();
            })
            .catch(function (err) { flash(err.message || 'Save failed', false); })
            .finally(function () { b.disabled = false; });
    });

    function loadAll() {
        return Promise.all([apiGet(), apiGet('preview')]).then(function (all) {
            var res = all[0] || {};
            var previewRes = all[1] || {};
            var map = {};
            var previewMap = {};
            if (res.success && Array.isArray(res.data)) {
                res.data.forEach(function (r) { map[r.country_slug] = { labels: r.labels || {}, requirements: r.requirements || [] }; });
            }
            if (previewRes.success && previewRes.data && typeof previewRes.data === 'object') {
                previewMap = previewRes.data;
            }
            render(map, previewMap);
        }).catch(function () {
            render({}, {});
            flash('Loaded defaults (API read failed)', false);
        });
    }

    if (exportBtn) {
        exportBtn.addEventListener('click', function () {
            apiGet('export').then(function (res) {
                if (!res.success) throw new Error(res.message || 'Export failed');
                var blob = new Blob([JSON.stringify(res, null, 2)], { type: 'application/json;charset=utf-8' });
                var a = document.createElement('a');
                var stamp = new Date().toISOString().replace(/[:.]/g, '-');
                a.href = URL.createObjectURL(blob);
                a.download = 'country-profiles-' + stamp + '.json';
                document.body.appendChild(a);
                a.click();
                setTimeout(function () {
                    URL.revokeObjectURL(a.href);
                    a.remove();
                }, 0);
            }).catch(function (err) {
                flash(err.message || 'Export failed', false);
            });
        });
    }

    if (importBtn && importInput) {
        importBtn.addEventListener('click', function () { importInput.click(); });
        importInput.addEventListener('change', function () {
            var file = importInput.files && importInput.files[0];
            if (!file) return;
            var reader = new FileReader();
            reader.onload = function () {
                try {
                    var parsed = JSON.parse(String(reader.result || '{}'));
                    var profiles = Array.isArray(parsed.profiles) ? parsed.profiles : [];
                    apiSave({ action: 'import', profiles: profiles }).then(function (res) {
                        if (!res.success) throw new Error(res.message || 'Import failed');
                        flash('Import completed', true);
                        loadAll();
                    }).catch(function (err) {
                        flash(err.message || 'Import failed', false);
                    });
                } catch (e) {
                    flash('Invalid JSON file', false);
                } finally {
                    importInput.value = '';
                }
            };
            reader.readAsText(file);
        });
    }

    loadAll();
})();

