(function () {
    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function initSupplierCommForm() {
        var form = document.querySelector('[data-supplier-comm-form]');
        if (!form || form.getAttribute('data-sc-bound') === '1') {
            return;
        }
        form.setAttribute('data-sc-bound', '1');

        var supplierSelect = form.querySelector('[name="supplier_id"]');
        var poSelect = form.querySelector('[name="purchase_order_id"]');
        var rfqSelect = form.querySelector('[name="rfq_id"]');
        var contactInput = form.querySelector('[name="supplier_contact"]');
        var phoneInput = form.querySelector('[name="supplier_phone"]');
        var emailInput = form.querySelector('[name="supplier_email"]');
        var subjectInput = form.querySelector('[name="subject"]');
        var bodyInput = form.querySelector('[name="body"]');
        var historyBody = document.getElementById('sc_supplier_history_body');
        var historyPanel = document.getElementById('sc_supplier_history');
        var historyUrl = form.getAttribute('data-history-url') || '';
        var profileUrl = form.getAttribute('data-supplier-profile-url') || '';
        var excludeId = parseInt(form.getAttribute('data-comm-id') || '0', 10);
        var channelActions = document.getElementById('sc_channel_actions');
        var actEmail = document.getElementById('sc_act_email');
        var actWhatsapp = document.getElementById('sc_act_whatsapp');
        var actPhone = document.getElementById('sc_act_phone');

        function renderHistory(rows) {
            if (!historyBody) {
                return;
            }
            var hint = historyPanel;
            var emptyMsg = hint ? (hint.getAttribute('data-empty') || '—') : '—';
            var hintMsg = hint ? (hint.getAttribute('data-hint') || '') : '';
            if (!rows || !rows.length) {
                historyBody.innerHTML = '<p class="text-muted small p-3 mb-0">' + escapeHtml(hintMsg || emptyMsg) + '</p>';
                return;
            }
            var colDate = hint ? (hint.getAttribute('data-col-date') || '') : '';
            var colSubject = hint ? (hint.getAttribute('data-col-subject') || '') : '';
            var colStatus = hint ? (hint.getAttribute('data-col-status') || '') : '';
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
            historyBody.innerHTML = html;
        }

        function loadHistory() {
            if (!historyBody || !historyUrl || !supplierSelect) {
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
                .then(function (r) {
                    if (!r.ok) {
                        return { rows: [] };
                    }
                    return r.json();
                })
                .then(function (data) { renderHistory(data.rows || []); })
                .catch(function () { renderHistory([]); });
        }

        function replaceSelectOptions(select, items, labelKey, valueKey, keepValue) {
            if (!select) {
                return;
            }
            var current = keepValue ? select.value : '';
            var html = '<option value=""></option>';
            (items || []).forEach(function (item) {
                var val = String(item[valueKey] || item.id || '');
                var label = String(item[labelKey] || val);
                html += '<option value="' + escapeHtml(val) + '">' + escapeHtml(label) + '</option>';
            });
            select.innerHTML = html;
            if (current && select.querySelector('option[value="' + current + '"]')) {
                select.value = current;
            }
        }

        function fillIfEmpty(input, value) {
            if (!input || !value) {
                return;
            }
            if (!String(input.value || '').trim()) {
                input.value = value;
            }
        }

        function fillField(input, value) {
            if (!input) {
                return;
            }
            input.value = value || '';
        }

        function fillContactFields(profile, force) {
            if (!profile) {
                if (force) {
                    fillField(contactInput, '');
                    fillField(phoneInput, '');
                    fillField(emailInput, '');
                }
                return;
            }
            if (force) {
                fillField(contactInput, profile.name || '');
                fillField(phoneInput, profile.phone || '');
                fillField(emailInput, profile.email || '');
            } else {
                fillIfEmpty(contactInput, profile.name || '');
                fillIfEmpty(phoneInput, profile.phone || '');
                fillIfEmpty(emailInput, profile.email || '');
            }
        }

        function updateChannelActions() {
            if (!channelActions) {
                return;
            }
            var email = emailInput ? String(emailInput.value || '').trim() : '';
            var phone = phoneInput ? String(phoneInput.value || '').trim() : '';
            var subject = subjectInput ? String(subjectInput.value || '').trim() : '';
            var body = bodyInput ? String(bodyInput.value || '').trim() : '';
            var hasAction = false;

            if (actEmail) {
                if (email) {
                    var mailBody = encodeURIComponent(body);
                    var mailSub = encodeURIComponent(subject);
                    actEmail.href = 'mailto:' + encodeURIComponent(email) + '?subject=' + mailSub + '&body=' + mailBody;
                    actEmail.hidden = false;
                    hasAction = true;
                } else {
                    actEmail.hidden = true;
                }
            }
            if (actWhatsapp) {
                var digits = phone.replace(/\D+/g, '');
                if (digits) {
                    actWhatsapp.href = 'https://wa.me/' + digits + '?text=' + encodeURIComponent(body || subject);
                    actWhatsapp.hidden = false;
                    hasAction = true;
                } else {
                    actWhatsapp.hidden = true;
                }
            }
            if (actPhone) {
                if (phone) {
                    actPhone.href = 'tel:' + phone;
                    actPhone.hidden = false;
                    hasAction = true;
                } else {
                    actPhone.hidden = true;
                }
            }
            channelActions.hidden = !hasAction;
        }

        function loadSupplierProfile(force) {
            var sid = supplierSelect ? parseInt(supplierSelect.value, 10) : 0;
            loadHistory();
            if (sid < 1 || !profileUrl) {
                fillContactFields(null, force);
                updateChannelActions();
                return;
            }
            fetch(profileUrl + (profileUrl.indexOf('?') >= 0 ? '&' : '?') + 'supplier_id=' + sid, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            })
                .then(function (r) {
                    if (!r.ok) {
                        return { profile: null, purchase_orders: [], rfqs: [] };
                    }
                    return r.json();
                })
                .then(function (data) {
                    var profile = data.profile || null;
                    fillContactFields(profile, force);
                    replaceSelectOptions(poSelect, data.purchase_orders || [], 'order_no', 'id', true);
                    replaceSelectOptions(rfqSelect, data.rfqs || [], 'rfq_no', 'id', true);
                    updateChannelActions();
                })
                .catch(function () { updateChannelActions(); });
        }

        if (supplierSelect) {
            var initialSid = historyPanel
                ? parseInt(historyPanel.getAttribute('data-initial-supplier') || '0', 10)
                : 0;
            if (initialSid > 0 && supplierSelect.querySelector('option[value="' + initialSid + '"]')) {
                supplierSelect.value = String(initialSid);
            }
            supplierSelect.addEventListener('change', function () { loadSupplierProfile(true); });
            loadSupplierProfile(false);
        }

        [emailInput, phoneInput, subjectInput, bodyInput].forEach(function (el) {
            if (el) {
                el.addEventListener('input', updateChannelActions);
            }
        });
        updateChannelActions();
    }

    function boot() {
        initSupplierCommForm();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
    document.addEventListener('rateb:nav:afterEnter', boot);
})();
