(function () {
    var form = document.querySelector('[data-supplier-comm-form]');
    if (!form) {
        return;
    }
    var supplierSelect = form.querySelector('[name="supplier_id"]');
    var historyBox = document.getElementById('sc_supplier_history');
    var historyUrl = form.getAttribute('data-history-url') || '';
    var excludeId = parseInt(form.getAttribute('data-comm-id') || '0', 10);

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderHistory(rows) {
        if (!historyBox) {
            return;
        }
        if (!rows || !rows.length) {
            historyBox.innerHTML = '<p class="text-muted small p-3 mb-0">' + escapeHtml(historyBox.getAttribute('data-empty') || '—') + '</p>';
            return;
        }
        var colDate = historyBox.getAttribute('data-col-date') || '';
        var colSubject = historyBox.getAttribute('data-col-subject') || '';
        var colStatus = historyBox.getAttribute('data-col-status') || '';
        var html = '<div class="table-responsive"><table class="table table-sm rateb-table mb-0"><thead><tr>';
        html += '<th>' + escapeHtml(colDate) + '</th>';
        html += '<th>' + escapeHtml(colSubject) + '</th>';
        html += '<th>' + escapeHtml(colStatus) + '</th>';
        html += '</tr></thead><tbody>';
        rows.forEach(function (row) {
            html += '<tr>';
            html += '<td>' + escapeHtml(row.comm_date || (row.created_at ? String(row.created_at).substring(0, 10) : '—')) + '</td>';
            html += '<td class="rateb-cell-clip">' + escapeHtml(row.subject || '') + '</td>';
            html += '<td><span class="badge bg-secondary">' + escapeHtml(row.comm_status_label || row.comm_status || '') + '</span></td>';
            html += '</tr>';
        });
        html += '</tbody></table></div>';
        historyBox.innerHTML = html;
    }

    function loadHistory() {
        if (!historyBox || !historyUrl || !supplierSelect) {
            return;
        }
        var sid = parseInt(supplierSelect.value, 10);
        if (sid < 1) {
            renderHistory([]);
            return;
        }
        var url = historyUrl + (historyUrl.indexOf('?') >= 0 ? '&' : '?') + 'supplier_id=' + sid;
        if (excludeId > 0) {
            url += '&exclude_id=' + excludeId;
        }
        fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) { renderHistory(data.rows || []); })
            .catch(function () { renderHistory([]); });
    }

    if (supplierSelect) {
        supplierSelect.addEventListener('change', loadHistory);
        loadHistory();
    }
})();
