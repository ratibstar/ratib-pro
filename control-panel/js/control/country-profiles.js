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
        'identity', 'password',
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
        return fetch(url, { credentials: 'same-origin' }).then(function (r) {
            if (!r.ok) {
                return r.text().then(function (t) {
                    throw new Error('HTTP ' + r.status);
                });
            }
            return r.json();
        });
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
    function baseDefaultsForSlug(slug) {
        return defaults[slug] || defaults.default || { labels: {}, requirements: [] };
    }

    function mergeEffectiveForDisplay(slug, previewMap, map) {
        var d = baseDefaultsForSlug(slug);
        var fromPreview = previewMap && previewMap[slug];
        var fromStore = map && map[slug];
        var labels = Object.assign({}, d.labels || {}, (fromStore && fromStore.labels) || {}, (fromPreview && fromPreview.labels) || {});
        var req = [];
        if (fromPreview && Array.isArray(fromPreview.requirements) && fromPreview.requirements.length) {
            req = fromPreview.requirements.slice();
        } else if (fromStore && Array.isArray(fromStore.requirements) && fromStore.requirements.length) {
            req = fromStore.requirements.slice();
        } else {
            req = (d.requirements || []).slice();
        }
        return { labels: labels, requirements: req };
    }

    /**
     * One card per active control_countries row, plus Default (fallback), plus any slug only present in saved JSON.
     */
    function computeSlugOrder(registry, map) {
        var seen = {};
        var order = [];
        var titles = {};

        function pushOrphanSlugs() {
            if (!map) return;
            Object.keys(map).forEach(function (s) {
                if (!seen[s]) {
                    seen[s] = true;
                    order.push(s);
                    titles[s] = titles[s] || s;
                }
            });
        }

        if (!registry || registry.length === 0) {
            Object.keys(defaults).forEach(function (s) {
                if (seen[s]) return;
                seen[s] = true;
                order.push(s);
                titles[s] = s === 'default' ? 'Default (fallback)' : s;
            });
            pushOrphanSlugs();
        } else {
            registry.forEach(function (r) {
                var s = String(r.slug || '').toLowerCase().trim();
                if (!s || seen[s]) return;
                seen[s] = true;
                order.push(s);
                titles[s] = (r.name && String(r.name).trim()) ? String(r.name).trim() : s;
            });
            if (!seen.default) {
                seen.default = true;
                order.push('default');
                titles.default = 'Default (fallback)';
            }
            pushOrphanSlugs();
        }
        return { order: order, titles: titles };
    }

    function buildPreviewMerged(slug, previewMap, map) {
        var eff = mergeEffectiveForDisplay(slug, previewMap, map);
        var labels = eff.labels || {};
        var req = eff.requirements || [];
        var reqText = req.length ? req.join(', ') : '(built-in default requirement list for this country)';
        return '<div class="col-12 mt-2">' +
            '<div class="cp-effective-preview p-3">' +
            '<div class="cp-preview-title">Effective preview (read-only — same labels + requirement keys as worker form)</div>' +
            '<div class="cp-preview-line"><strong>Government</strong> — ' + esc(labels.government || '') + '</div>' +
            '<div class="cp-preview-line"><strong>Work permit</strong> — ' + esc(labels.workPermit || '') + '</div>' +
            '<div class="cp-preview-line"><strong>Contract</strong> — ' + esc(labels.contract || '') + '</div>' +
            '<div class="cp-preview-line"><strong>Travel</strong> — ' + esc(labels.travel || '') + '</div>' +
            '<div class="cp-preview-line mt-2"><strong>Required fields</strong></div>' +
            '<div class="cp-preview-req">' + esc(reqText) + '</div>' +
            '</div></div>';
    }

    function buildCard(slug, map, previewMap, titles) {
        var eff = mergeEffectiveForDisplay(slug, previewMap || {}, map || {});
        var labels = eff.labels || {};
        var requirements = eff.requirements || [];
        var headTitle = (titles && titles[slug]) ? titles[slug] : slug;
        var chips = availableRequirementFields.map(function (f) {
            return '<button type="button" class="btn btn-outline-secondary btn-sm cp-chip me-1 mb-1" name="cp-chip-' + esc(slug) + '-' + esc(f) + '" data-slug="' + esc(slug) + '" data-field="' + esc(f) + '">' + esc(f) + '</button>';
        }).join('');
        var idGov = 'cp-' + slug + '-label-government';
        var idWp = 'cp-' + slug + '-label-workPermit';
        var idCt = 'cp-' + slug + '-label-contract';
        var idTr = 'cp-' + slug + '-label-travel';
        var idReq = 'cp-' + slug + '-requirements';
        return '<div class="card gov-card"><div class="card-body">' +
            '<div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">' +
                '<div><div class="mb-0 fw-semibold">' + esc(headTitle) + '</div>' +
                '<div class="small text-muted text-uppercase" style="letter-spacing:0.04em;">' + esc(slug) + '</div></div>' +
                '<div>' +
                    '<button type="button" class="btn btn-sm btn-outline-light cp-load-default me-1" name="cp-load-default-' + esc(slug) + '" data-slug="' + esc(slug) + '">Import Defaults</button>' +
                    '<button type="button" class="btn btn-sm btn-primary cp-save" name="cp-save-' + esc(slug) + '" data-slug="' + esc(slug) + '">Save</button>' +
                '</div>' +
            '</div>' +
            '<div class="row g-2 gov-form">' +
            '<div class="col-md-3"><label class="form-label visually-hidden" for="' + idGov + '">Government label</label>' +
            '<input type="text" class="form-control form-control-sm cp-label" id="' + idGov + '" name="cp-label-' + esc(slug) + '-government" autocomplete="off" data-slug="' + esc(slug) + '" data-key="government" value="' + esc(labels.government || '') + '" placeholder="Government label"></div>' +
            '<div class="col-md-3"><label class="form-label visually-hidden" for="' + idWp + '">Work permit label</label>' +
            '<input type="text" class="form-control form-control-sm cp-label" id="' + idWp + '" name="cp-label-' + esc(slug) + '-workPermit" autocomplete="off" data-slug="' + esc(slug) + '" data-key="workPermit" value="' + esc(labels.workPermit || '') + '" placeholder="Work Permit label"></div>' +
            '<div class="col-md-3"><label class="form-label visually-hidden" for="' + idCt + '">Contract label</label>' +
            '<input type="text" class="form-control form-control-sm cp-label" id="' + idCt + '" name="cp-label-' + esc(slug) + '-contract" autocomplete="off" data-slug="' + esc(slug) + '" data-key="contract" value="' + esc(labels.contract || '') + '" placeholder="Contract label"></div>' +
            '<div class="col-md-3"><label class="form-label visually-hidden" for="' + idTr + '">Travel label</label>' +
            '<input type="text" class="form-control form-control-sm cp-label" id="' + idTr + '" name="cp-label-' + esc(slug) + '-travel" autocomplete="off" data-slug="' + esc(slug) + '" data-key="travel" value="' + esc(labels.travel || '') + '" placeholder="Travel label"></div>' +
            '<div class="col-12"><label class="form-label small text-muted" for="' + idReq + '">Comma-separated required field keys</label>' +
            '<textarea class="form-control form-control-sm cp-req" id="' + idReq + '" name="cp-req-' + esc(slug) + '" rows="3" spellcheck="false" data-slug="' + esc(slug) + '" placeholder="e.g. full_name, gender, agent_id">' + esc(requirements.join(', ')) + '</textarea></div>' +
            '<div class="col-12"><div class="small text-muted mb-1">Requirement field picker</div>' + chips + '</div>' +
            buildPreviewMerged(slug, previewMap, map) +
            '</div></div></div>';
    }

    function render(map, previewMap, registry) {
        var meta = computeSlugOrder(registry || [], map || {});
        var html = '';
        meta.order.forEach(function (slug) {
            html += buildCard(slug, map || {}, previewMap || {}, meta.titles || {});
        });
        wrap.innerHTML = html;
    }

    wrap.addEventListener('click', function (e) {
        var importBtn = e.target.closest('.cp-load-default');
        if (importBtn) {
            var importSlug = importBtn.getAttribute('data-slug');
            var d = defaults[importSlug] || defaults.default;
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
            var registry = [];
            if (res.success && Array.isArray(res.data)) {
                res.data.forEach(function (r) { map[r.country_slug] = { labels: r.labels || {}, requirements: r.requirements || [] }; });
            }
            if (res.success && Array.isArray(res.registry)) {
                registry = res.registry;
            }
            if (res.scope && res.scope.restricted) {
                if (exportBtn) exportBtn.classList.add('d-none');
                if (importBtn) importBtn.classList.add('d-none');
            } else {
                if (exportBtn) exportBtn.classList.remove('d-none');
                if (importBtn) importBtn.classList.remove('d-none');
            }
            if (previewRes.success && previewRes.data && typeof previewRes.data === 'object') {
                previewMap = previewRes.data;
            }
            render(map, previewMap, registry);
        }).catch(function () {
            render({}, {}, []);
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

