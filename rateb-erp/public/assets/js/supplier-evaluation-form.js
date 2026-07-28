(function () {
    var form = document.querySelector('[data-supplier-evaluation-form]');
    if (!form) {
        return;
    }

    var scoreNames = ['quality_score', 'delivery_score', 'price_score', 'service_score'];
    var overallEl = document.getElementById('eval_overall_display');
    var percentEl = document.getElementById('eval_percent_display');
    var tierEl = document.getElementById('eval_tier_display');
    var tierInput = document.getElementById('eval_tier_input');
    var supplierSelect = form.querySelector('[name="supplier_id"]');
    var historyBox = document.getElementById('eval_supplier_history');
    var historyUrl = form.getAttribute('data-history-url') || '';

    function tierLabel(tier) {
        var labels = {};
        try {
            labels = JSON.parse(form.getAttribute('data-tier-labels') || '{}');
        } catch (e) {
            labels = {};
        }
        return labels[tier] || tier;
    }

    function tierBadge(tier) {
        var cls = 'secondary';
        if (tier === 'excellent') cls = 'success';
        else if (tier === 'very_good') cls = 'primary';
        else if (tier === 'good') cls = 'info';
        else if (tier === 'weak') cls = 'warning';
        return '<span class="badge bg-' + cls + '">' + tierLabel(tier) + '</span>';
    }

    function recalcScores() {
        var sum = 0;
        var count = 0;
        scoreNames.forEach(function (name) {
            var el = form.querySelector('[name="' + name + '"]');
            if (!el) return;
            var v = parseInt(el.value, 10);
            if (!isNaN(v)) {
                sum += v;
                count++;
            }
        });
        var overall = count > 0 ? Math.round((sum / count) * 100) / 100 : 0;
        var percent = Math.round(overall * 10 * 10) / 10;
        var tier = 'weak';
        if (overall >= 9) tier = 'excellent';
        else if (overall >= 7.5) tier = 'very_good';
        else if (overall >= 5) tier = 'good';
        if (overallEl) overallEl.textContent = overall.toFixed(2);
        if (percentEl) percentEl.textContent = percent.toFixed(1) + '%';
        if (tierEl) tierEl.innerHTML = tierBadge(tier);
        if (tierInput) tierInput.value = tier;
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderHistory(rows) {
        if (!historyBox) return;
        if (!rows || !rows.length) {
            historyBox.innerHTML = '<p class="text-muted small mb-0">' + (historyBox.getAttribute('data-empty') || '—') + '</p>';
            return;
        }
        var html = '<div class="table-responsive"><table class="table table-sm rateb-table mb-0"><thead><tr>';
        html += '<th>' + (historyBox.getAttribute('data-col-date') || '') + '</th>';
        html += '<th>' + (historyBox.getAttribute('data-col-overall') || '') + '</th>';
        html += '<th>' + (historyBox.getAttribute('data-col-tier') || '') + '</th>';
        html += '<th>' + (historyBox.getAttribute('data-col-approval') || '') + '</th>';
        html += '</tr></thead><tbody>';
        rows.forEach(function (row) {
            html += '<tr>';
            html += '<td>' + escapeHtml(row.evaluation_date || '—') + '</td>';
            html += '<td>' + escapeHtml(row.overall_score != null ? row.overall_score : '—') + '</td>';
            html += '<td>' + tierBadge(row.rating_tier || 'weak') + '</td>';
            html += '<td><span class="badge bg-secondary">' + escapeHtml(row.manager_approval_label || row.manager_approval || '') + '</span></td>';
            html += '</tr>';
        });
        html += '</tbody></table></div>';
        historyBox.innerHTML = html;
    }

    function loadHistory() {
        if (!historyBox || !historyUrl || !supplierSelect) return;
        var sid = parseInt(supplierSelect.value, 10);
        if (sid < 1) {
            renderHistory([]);
            return;
        }
        var excludeId = parseInt(form.getAttribute('data-evaluation-id') || '0', 10);
        var url = historyUrl + (historyUrl.indexOf('?') >= 0 ? '&' : '?') + 'supplier_id=' + sid;
        if (excludeId > 0) url += '&exclude_id=' + excludeId;
        fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (r) {
                if (!r.ok) {
                    return r.json().catch(function () { return { rows: [], error: '' }; });
                }
                return r.json();
            })
            .then(function (data) {
                if (data && data.error) {
                    renderHistory([]);
                    return;
                }
                renderHistory(data.rows || []);
            })
            .catch(function () { renderHistory([]); });
    }

    function isFormClean() {
        if (!supplierSelect || parseInt(supplierSelect.value, 10) > 0) {
            return false;
        }
        for (var i = 0; i < scoreNames.length; i++) {
            var el = form.querySelector('[name="' + scoreNames[i] + '"]');
            if (el && String(el.value || '').trim() !== '') {
                return false;
            }
        }
        var comments = form.querySelector('[name="comments"]');
        if (comments && String(comments.value || '').trim() !== '') {
            return false;
        }
        var periodStart = form.querySelector('[name="period_start"]');
        var periodEnd = form.querySelector('[name="period_end"]');
        if (periodStart && String(periodStart.value || '').trim() !== '') {
            return false;
        }
        if (periodEnd && String(periodEnd.value || '').trim() !== '') {
            return false;
        }
        return true;
    }

    function addSupplierRefreshButton() {
        if (!supplierSelect) {
            return;
        }
        var parent = supplierSelect.parentElement;
        if (!parent) {
            return;
        }
        var wrapper = document.createElement('div');
        wrapper.className = 'd-flex align-items-center gap-2';
        parent.insertBefore(wrapper, supplierSelect);
        wrapper.appendChild(supplierSelect);
        supplierSelect.classList.add('flex-grow-1');
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-outline-secondary btn-sm';
        btn.title = form.getAttribute('data-supplier-refresh-title') || 'Refresh suppliers';
        btn.setAttribute('aria-label', btn.title);
        btn.innerHTML = '<i class="fas fa-sync-alt"></i>';
        btn.addEventListener('click', function () {
            if (isFormClean() || window.confirm('Unsaved changes may be lost. Reload?')) {
                window.location.reload();
            }
        });
        wrapper.appendChild(btn);
    }

    scoreNames.forEach(function (name) {
        var el = form.querySelector('[name="' + name + '"]');
        if (el) el.addEventListener('change', recalcScores);
    });
    if (supplierSelect) supplierSelect.addEventListener('change', loadHistory);
    recalcScores();
    loadHistory();
    addSupplierRefreshButton();

    window.addEventListener('pageshow', function (e) {
        if (e.persisted && isFormClean()) {
            window.location.reload();
        }
    });
})();
