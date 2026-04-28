/**
 * EN: Implements control-panel module behavior and admin-country operations in `control-panel/js/control/registration-requests-page.js`.
 * AR: ينفذ سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/js/control/registration-requests-page.js`.
 */
(function() {
    var body = document.body;
    var contentEl = document.getElementById('registrationRequestsContent');
    var API_BASE = (contentEl && contentEl.getAttribute('data-api-base')) || (body && body.getAttribute('data-api-base')) || '';
    var AGENCIES_URL = (contentEl && contentEl.getAttribute('data-agencies-url')) || (body && body.getAttribute('data-agencies-url')) || '';
    if (!API_BASE) API_BASE = (window.location.origin + (window.location.pathname.replace(/\/pages\/.*$/, '') || '') + '/api/control').replace(/\/+$/, '');

    // Move modals to body so they display correctly
    ['viewModal', 'editModal', 'approveModal', 'alertModal', 'confirmModal'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el && el.parentNode !== document.body) document.body.appendChild(el);
    });

    function toWesternNum(s) {
        var m = {'٠':'0','١':'1','٢':'2','٣':'3','٤':'4','٥':'5','٦':'6','٧':'7','٨':'8','٩':'9','۰':'0','۱':'1','۲':'2','۳':'3','۴':'4','۵':'5','۶':'6','۷':'7','۸':'8','۹':'9'};
        return String(s).replace(/[٠-٩۰-۹]/g, function(d) { return m[d] || d; });
    }
    /** Western digits only, suitable for <input> value (avoids Eastern Arabic display from locale). */
    function westernAmountInputString(raw) {
        if (raw == null || raw === '') return '';
        var ws = toWesternNum(String(raw).trim());
        var n = parseFloat(ws);
        return isFinite(n) ? String(n) : '';
    }
    function westernYearsInputString(raw) {
        if (raw == null || raw === '') return '';
        var wy = toWesternNum(String(raw).trim());
        var yi = parseInt(wy, 10);
        return wy !== '' && !isNaN(yi) ? String(yi) : '';
    }
    /** Gregorian English (e.g. Mar 23, 2026, 14:30:00) */
    function fmtEnDateTime(raw) {
        if (raw == null || raw === '') return '-';
        var s = String(raw).trim();
        var d = new Date(s.indexOf('T') >= 0 ? s : s.replace(' ', 'T'));
        if (isNaN(d.getTime())) {
            return s.length > 19 ? s.substring(0, 19) : s;
        }
        return d.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
    }
    function escapeHtml(s) {
        if (s == null || s === '') return '';
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }
    function showAlert(msg) {
        var el = document.getElementById('alertMessage');
        var modalEl = document.getElementById('alertModal');
        if (el) el.textContent = msg;
        if (modalEl && typeof bootstrap !== 'undefined') {
            new bootstrap.Modal(modalEl).show();
            return;
        }
        window.alert(msg);
    }
    function showConfirm(msg) {
        return new Promise(function(resolve) {
            var confirmMessage = document.getElementById('confirmMessage');
            var modalEl = document.getElementById('confirmModal');
            if (confirmMessage) confirmMessage.textContent = msg;
            if (!modalEl || typeof bootstrap === 'undefined') {
                resolve(window.confirm(msg));
                return;
            }
            var modal = new bootstrap.Modal(modalEl);
            var done = false;
            var finish = function(ok) { if (done) return; done = true; modal.hide(); resolve(ok); };
            modalEl.querySelector('#confirmOk').onclick = function() { finish(true); };
            modalEl.querySelector('#confirmCancel').onclick = function() { finish(false); };
            modalEl.addEventListener('hidden.bs.modal', function() { finish(false); }, { once: true });
            modal.show();
        });
    }

    var regLinkSelect = document.getElementById('regLinkSelect');
    var btnCopyLink = document.getElementById('btnCopyLink');
    if (regLinkSelect) regLinkSelect.addEventListener('change', function() { document.getElementById('regLink').value = this.value; });
    if (btnCopyLink) btnCopyLink.onclick = function() {
        var inp = document.getElementById('regLink');
        if (inp) { inp.select(); document.execCommand('copy'); showAlert('Link copied to clipboard'); }
    };
    document.querySelectorAll('.js-auto-submit').forEach(function(el) {
        if (!el.form) return;
        el.addEventListener('change', function() { el.form.submit(); });
    });
    var btnDismissPendingAlert = document.getElementById('btnDismissPendingAlert');
    var pendingAlertBanner = document.getElementById('pendingAlertBanner');
    if (btnDismissPendingAlert && pendingAlertBanner) {
        btnDismissPendingAlert.addEventListener('click', function() {
            pendingAlertBanner.classList.add('d-none');
        });
    }

    var reqFpLocale = {
        weekdays: { shorthand: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'], longhand: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] },
        months: { shorthand: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'], longhand: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'] },
        firstDayOfWeek: 0,
        rangeSeparator: ' to ',
        weekAbbreviation: 'Wk'
    };
    function initRegistrationDatePickers() {
        if (typeof flatpickr === 'undefined') return;
        try {
            if (flatpickr.localize) flatpickr.localize(reqFpLocale);
        } catch (e) { /* ignore */ }
        document.querySelectorAll('input.req-date-filter').forEach(function(inp) {
            if (inp._flatpickr) return;
            var initial = (inp.value || '').trim();
            flatpickr(inp, {
                locale: reqFpLocale,
                dateFormat: 'Y-m-d',
                allowInput: true,
                disableMobile: true,
                clickOpens: true,
                defaultDate: initial || undefined
            });
        });
    }
    initRegistrationDatePickers();

    document.querySelectorAll('input.req-date-en').forEach(function(inp) {
        inp.addEventListener('input', function() {
            this.value = toWesternNum(this.value || '');
        });
        inp.addEventListener('blur', function() {
            this.value = toWesternNum((this.value || '').replace(/\//g, '-'));
        });
    });

    (function wireEditWesternNumericFields() {
        function bind(el) {
            if (!el || el._reqWesternNumBound) return;
            el._reqWesternNumBound = true;
            el.setAttribute('lang', 'en');
            el.setAttribute('dir', 'ltr');
            el.addEventListener('input', function() {
                this.value = toWesternNum(this.value || '');
            });
            el.addEventListener('blur', function() {
                this.value = toWesternNum(String(this.value || '').trim());
            });
        }
        bind(document.getElementById('editPlanAmount'));
        bind(document.getElementById('editYears'));
    })();

    function api(path, method, body) {
        var url = API_BASE + path + (path.indexOf('?') >= 0 ? '&' : '?') + 'control=1';
        var opts = { method: method, headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin' };
        if (body && (method === 'POST' || method === 'PUT' || method === 'PATCH' || method === 'DELETE')) opts.body = JSON.stringify(body);
        return fetch(url, opts).then(function(r) {
            var ct = r.headers.get('content-type');
            if (!ct || ct.indexOf('application/json') === -1) return r.text().then(function(t) { throw new Error(t || r.status); });
            return r.json();
        });
    }
    function getSelectedRows() {
        return Array.prototype.slice.call(document.querySelectorAll('.req-row-check:checked'))
            .map(function(chk) { return chk.closest('tr'); })
            .filter(Boolean);
    }
    function getSelectedRequestIds() {
        return getSelectedRows().map(function(row) { return parseInt(row.getAttribute('data-id') || '0', 10); }).filter(function(id) { return id > 0; });
    }
    function updateSelectedCount() {
        var selected = getSelectedRequestIds().length;
        var countEl = document.getElementById('reqBulkSelectedCount');
        if (countEl) countEl.textContent = String(selected);
        var checkAllEl = document.getElementById('reqCheckAll');
        var allRows = document.querySelectorAll('.req-row-check');
        if (checkAllEl) {
            checkAllEl.checked = allRows.length > 0 && selected === allRows.length;
            checkAllEl.indeterminate = selected > 0 && selected < allRows.length;
        }
    }
    function requireSelection() {
        var ids = getSelectedRequestIds();
        if (!ids.length) {
            showAlert('Please select at least one row first.');
            return null;
        }
        return ids;
    }
    function getSelectedAgencyIds() {
        var idsMap = {};
        getSelectedRows().forEach(function(row) {
            var aid = parseInt(row.getAttribute('data-created-agency-id') || '0', 10);
            if (aid > 0) idsMap[aid] = true;
        });
        return Object.keys(idsMap).map(function(k) { return parseInt(k, 10); });
    }
    function getSelectedAgencyNamesWithoutCreatedId() {
        var namesMap = {};
        getSelectedRows().forEach(function(row) {
            var aid = parseInt(row.getAttribute('data-created-agency-id') || '0', 10);
            if (aid > 0) return;
            var cell = row.querySelector('td.req-col-agency');
            var nm = cell ? (cell.textContent || '').trim() : '';
            if (!nm || nm === '-') return;
            namesMap[nm.toLowerCase()] = nm;
        });
        return Object.keys(namesMap).map(function(k) { return namesMap[k]; });
    }
    function resolveAgencyIdsByNames(names) {
        if (!names || !names.length) return Promise.resolve([]);
        return Promise.all(names.map(function(name) {
            var q = encodeURIComponent(name);
            return api('/agencies.php?limit=25&search=' + q, 'GET')
                .then(function(res) {
                    if (!res || !res.success || !Array.isArray(res.list)) return 0;
                    var exact = res.list.find(function(a) {
                        return String(a && a.name ? a.name : '').trim().toLowerCase() === String(name).trim().toLowerCase();
                    });
                    var match = exact || res.list[0];
                    var id = match ? parseInt(match.id || '0', 10) : 0;
                    return id > 0 ? id : 0;
                })
                .catch(function() { return 0; });
        })).then(function(ids) {
            var seen = {};
            return ids.filter(function(id) {
                if (!id || seen[id]) return false;
                seen[id] = true;
                return true;
            });
        });
    }
    function flashRows(rows, cssClass) {
        rows.forEach(function(row) {
            row.classList.remove('req-row-updated-paid', 'req-row-updated-rejected', 'req-row-updated-active', 'req-row-updated-warning');
            row.classList.add(cssClass);
        });
        setTimeout(function() {
            rows.forEach(function(row) { row.classList.remove(cssClass); });
        }, 2200);
    }
    var REQ_ROW_VISUAL_CLASSES = ['req-row-agency-inactive', 'req-row-agency-suspended', 'req-row-agency-ok', 'req-row-req-pending', 'req-row-req-rejected', 'req-row-req-approved'];
    function stripReqRowVisualClasses(row) {
        REQ_ROW_VISUAL_CLASSES.forEach(function(c) { row.classList.remove(c); });
    }
    function applyRegistrationRowVisual(row) {
        stripReqRowVisualClasses(row);
        var aid = parseInt(row.getAttribute('data-created-agency-id') || '0', 10);
        var st = (row.getAttribute('data-status') || 'pending').toLowerCase();
        if (aid > 0 && row.hasAttribute('data-agency-is-active')) {
            var inactive = row.getAttribute('data-agency-is-active') === '0';
            var suspended = row.getAttribute('data-agency-suspended') === '1';
            if (inactive) row.classList.add('req-row-agency-inactive');
            if (suspended) row.classList.add('req-row-agency-suspended');
            if (!inactive && !suspended) row.classList.add('req-row-agency-ok');
        } else {
            if (st === 'pending') row.classList.add('req-row-req-pending');
            else if (st === 'rejected') row.classList.add('req-row-req-rejected');
            else if (st === 'approved') row.classList.add('req-row-req-approved');
        }
    }
    function refreshAllRegistrationRowVisuals() {
        document.querySelectorAll('.req-table tbody tr[data-id]').forEach(applyRegistrationRowVisual);
    }
    function clearSelection() {
        document.querySelectorAll('.req-row-check').forEach(function(chk) { chk.checked = false; });
        updateSelectedCount();
    }

    var approveModalEl = document.getElementById('approveModal');
    var viewModalEl = document.getElementById('viewModal');
    var editModalEl = document.getElementById('editModal');
    var approveModal = (approveModalEl && typeof bootstrap !== 'undefined') ? new bootstrap.Modal(approveModalEl) : null;
    var viewModal = (viewModalEl && typeof bootstrap !== 'undefined') ? new bootstrap.Modal(viewModalEl) : null;
    var editModal = (editModalEl && typeof bootstrap !== 'undefined') ? new bootstrap.Modal(editModalEl) : null;

    var PENDING_ALERT_KEY = 'regRequestsPendingAlertStopped';
    var pendingAlertInterval = null;
    function stopPendingAlerts() {
        try { sessionStorage.setItem(PENDING_ALERT_KEY, '1'); } catch (e) {}
        if (pendingAlertInterval) { clearInterval(pendingAlertInterval); pendingAlertInterval = null; }
        var banner = document.getElementById('pendingAlertBanner');
        if (banner) banner.style.display = 'none';
    }
    function readPendingFilteredTotal() {
        var el = contentEl;
        if (!el) return -1;
        var raw = el.getAttribute('data-pending-filtered-total');
        if (raw == null || raw === '') return -1;
        var n = parseInt(raw, 10);
        return isNaN(n) ? -1 : n;
    }
    function checkPendingAndAlert() {
        var fromPage = readPendingFilteredTotal();
        if (fromPage >= 0) {
            applyPendingBanner(fromPage);
            return;
        }
        api('/registration-requests.php?status=pending&limit=1', 'GET').then(function(res) {
            if (!res.success || !res.pagination) return;
            applyPendingBanner((res.pagination.total || 0) | 0);
        }).catch(function() {});
    }
    function applyPendingBanner(total) {
        if (total === 0) {
            var b0 = document.getElementById('pendingAlertBanner');
            if (b0) b0.style.display = 'none';
            return;
        }
        try { if (sessionStorage.getItem(PENDING_ALERT_KEY) === '1') return; } catch (e) {}
        var countEl = document.getElementById('pendingAlertCount');
        var banner = document.getElementById('pendingAlertBanner');
        if (countEl) countEl.textContent = total;
        if (banner) banner.style.display = 'flex';
        if ('Notification' in window && Notification.permission === 'granted') {
            try { new Notification('Registration Requests', { body: total + ' pending registration request(s) need your attention.' }); } catch (n) {}
        }
    }
    if (typeof sessionStorage !== 'undefined') {
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission().then(function() { checkPendingAndAlert(); });
        }
        pendingAlertInterval = setInterval(checkPendingAndAlert, 10000);
        setTimeout(checkPendingAndAlert, 500);
    }

    function fmtId(id) { return 'REQ' + String(id).padStart(4, '0'); }
    function fmtAgencyId(id) { return id ? ('AG' + String(id).padStart(4, '0')) : '-'; }
    /** Date only — matches table "Created" column (no time). */
    function fmtEnDateOnly(raw) {
        if (raw == null || raw === '') return '-';
        var s = String(raw).trim();
        var d = new Date(s.indexOf('T') >= 0 ? s : s.replace(' ', 'T'));
        if (isNaN(d.getTime())) return s.length >= 10 ? s.substring(0, 10) : '-';
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }
    function pickStr() {
        var a = arguments;
        for (var i = 0; i < a.length; i++) {
            var v = a[i];
            if (v != null && String(v).trim() !== '') return String(v).trim();
        }
        return '';
    }
    function htmlCreatedAgencyLink(r) {
        var aid = parseInt(r.created_agency_id, 10) || 0;
        var mcid = parseInt(r._manage_country_id, 10) || parseInt(r.country_id, 10) || 0;
        var cnm = pickStr(r.country_name, r.country, r.reg_country_name);
        if (!aid) return '-';
        if (!AGENCIES_URL) return escapeHtml(fmtAgencyId(aid));
        var qs = mcid > 0 ? ('control=1&country_id=' + encodeURIComponent(String(mcid))) : ('control=1&agency_id=' + encodeURIComponent(String(aid)));
        var agHtml = '<a href="' + escapeHtml(AGENCIES_URL + '?' + qs) + '">' + escapeHtml(fmtAgencyId(aid)) + '</a>';
        if (cnm) agHtml += ' <span class="text-muted">(' + escapeHtml(cnm) + ')</span>';
        return agHtml;
    }

    var viewModalRowData = null;

    function openViewModal(r) {
        stopPendingAlerts();
        viewModalRowData = r;
        function setView(id, content) {
            var el = document.getElementById(id);
            if (el) { if (typeof content === 'string') el.textContent = content; else el.innerHTML = content; }
        }
        function setViewHtml(id, html) {
            var el = document.getElementById(id);
            if (el) el.innerHTML = html != null && html !== '' ? String(html) : '-';
        }
        setView('viewReqId', fmtId(r.id));
        setView('viewId', fmtId(r.id));
        setView('viewCreated', fmtEnDateOnly(r.created_at));
        setView('viewAgency', r.agency_name || '-');
        setView('viewAgencyIdUser', pickStr(r.agency_id, r.reg_agency_id, r.agency_code) || '-');
        setView('viewCountry', pickStr(r.country_name, r.country, r.reg_country_name) || '-');
        var em = pickStr(r.contact_email);
        setViewHtml('viewEmail', em ? '<a href="mailto:' + escapeHtml(em) + '">' + escapeHtml(em) + '</a>' : '-');
        setView('viewPhone', pickStr(r.contact_phone, r.phone, r.reg_contact_phone) || '-');
        var siteUrl = pickStr(r.desired_site_url, r.site_url, r.reg_desired_site_url);
        setViewHtml('viewSiteUrl', (siteUrl && /^https?:\/\//i.test(siteUrl)) ? '<a href="' + escapeHtml(siteUrl) + '" target="_blank" rel="noopener">' + escapeHtml(siteUrl) + '</a>' : (siteUrl || '-'));
        setView('viewNotes', r.notes || '-');
        var planDisp = pickStr(r.plan, r.plan_key);
        setView('viewPlan', planDisp ? planDisp : '-');
        if (r.plan_amount != null && r.plan_amount !== '') {
            var amt = parseFloat(r.plan_amount);
            setView('viewAmount', isFinite(amt) ? ('$' + amt.toFixed(2)) : '-');
        } else {
            setView('viewAmount', '-');
        }
        var yearsVal = r.years != null && r.years !== '' ? r.years : null;
        setView('viewYears', yearsVal != null ? (yearsVal + ' year' + (parseInt(yearsVal, 10) > 1 ? 's' : '')) : '-');
        var payStatus = (r.payment_status || '').toLowerCase() || null;
        var payMethod = r.payment_method || null;
        if (payStatus || payMethod) {
            var payStatusText = payStatus ? payStatus.charAt(0).toUpperCase() + payStatus.slice(1) : 'N/A';
            var payMethodText = payMethod ? ' (' + escapeHtml(String(payMethod)) + ')' : '';
            var payBadgeClass = (payStatus === 'paid') ? 'badge-success' : ((payStatus === 'unpaid') ? 'badge-warning' : 'badge-secondary');
            setViewHtml('viewPayment', '<span class="badge ' + payBadgeClass + '">' + escapeHtml(payStatusText) + '</span>' + payMethodText);
        } else {
            setView('viewPayment', '-');
        }
        var s = (r.status || 'pending').toLowerCase();
        setViewHtml('viewStatus', '<span class="badge badge-' + s + '">' + s.charAt(0).toUpperCase() + s.slice(1) + '</span>');
        setView('viewUpdated', fmtEnDateTime(r.updated_at));
        setViewHtml('viewAgencyId', htmlCreatedAgencyLink(r));
        setView('viewIp', r.ip_address || '-');
        if (viewModal) viewModal.show();
    }

    function openEditModal(r) {
        document.getElementById('editId').value = r.id || '';
        document.getElementById('editReqId').textContent = 'REQ' + String(r.id || '').padStart(4, '0');
        document.getElementById('editAgencyName').value = r.agency_name || '';
        var agIdEl = document.getElementById('editAgencyId');
        if (agIdEl) agIdEl.value = pickStr(r.agency_id, r.reg_agency_id, r.agency_code);
        document.getElementById('editCountryName').value = pickStr(r.country_name, r.country, r.reg_country_name);
        document.getElementById('editContactEmail').value = r.contact_email || '';
        document.getElementById('editContactPhone').value = pickStr(r.contact_phone, r.phone, r.reg_contact_phone);
        document.getElementById('editSiteUrl').value = pickStr(r.desired_site_url, r.site_url, r.reg_desired_site_url);
        document.getElementById('editNotes').value = r.notes || '';
        var planSel = document.getElementById('editPlan');
        if (planSel) {
            var planVal = (r.plan || '').toLowerCase();
            planSel.value = planVal === 'pro' || planVal === 'gold' || planVal === 'platinum' ? planVal : '';
        }
        var amtInput = document.getElementById('editPlanAmount');
        if (amtInput) {
            amtInput.value = (r.plan_amount != null && r.plan_amount !== '') ? westernAmountInputString(r.plan_amount) : '';
        }
        var yearsInput = document.getElementById('editYears');
        if (yearsInput) {
            yearsInput.value = (r.years != null && r.years !== '') ? westernYearsInputString(r.years) : '';
        }
        var payStatusSel = document.getElementById('editPaymentStatus');
        if (payStatusSel) {
            var ps = (r.payment_status || '').toLowerCase();
            payStatusSel.value = (ps === 'pending' || ps === 'paid' || ps === 'unpaid' || ps === 'failed') ? ps : '';
        }
        var payMethodSel = document.getElementById('editPaymentMethod');
        if (payMethodSel) {
            var pm = (r.payment_method || '').toLowerCase();
            payMethodSel.value = (pm === 'paypal' || pm === 'tap' || pm === 'register') ? pm : '';
        }
        var createdRo = document.getElementById('editCreatedAtRo');
        if (createdRo) createdRo.value = fmtEnDateOnly(r.created_at);
        var stRo = document.getElementById('editStatusRo');
        if (stRo) {
            var stv = (r.status || 'pending').toLowerCase();
            stRo.value = stv.charAt(0).toUpperCase() + stv.slice(1);
        }
        var agWrap = document.getElementById('editCreatedAgencyRo');
        if (agWrap) agWrap.innerHTML = htmlCreatedAgencyLink(r);
        if (editModal) editModal.show();
    }

    function openApproveModal(json) {
        document.getElementById('approveRequestId').value = json.id || '';
        var approveCountryId = document.getElementById('approveCountryId');
        var preCountry = json.country_id || json._manage_country_id || '';
        if (approveCountryId) {
            approveCountryId.value = preCountry || (approveCountryId.options[1] ? approveCountryId.options[1].value : '');
        }
        document.getElementById('approveName').value = json.agency_name || '';
        document.getElementById('approveSlug').value = '';
        document.getElementById('approveSiteUrl').value = json.desired_site_url || '';
        document.getElementById('approveDbHost').value = 'localhost';
        document.getElementById('approveDbPort').value = 3306;
        document.getElementById('approveDbUser').value = '';
        document.getElementById('approveDbPass').value = '';
        document.getElementById('approveDbName').value = 'outratib_out';
        if (approveModal) approveModal.show();
    }

    document.addEventListener('click', function(e) {
        if (!e.target || typeof e.target.closest !== 'function') return;
        var btnView = e.target.closest('.btn-view');
        if (btnView) {
            e.preventDefault();
            e.stopPropagation();
            var raw = btnView.dataset.row || '';
            var r = raw ? JSON.parse(atob(raw)) : {};
            openViewModal(r);
            return;
        }
        var btnEdit = e.target.closest('.btn-edit');
        if (btnEdit) {
            e.preventDefault();
            e.stopPropagation();
            var raw = btnEdit.dataset.row || '';
            var r = raw ? JSON.parse(atob(raw)) : {};
            openEditModal(r);
            return;
        }
        var btnApprove = e.target.closest('.btn-approve');
        if (btnApprove) {
            e.preventDefault();
            e.stopPropagation();
            var row = btnApprove.closest('tr');
            var json = row && row.dataset.json ? JSON.parse(atob(row.dataset.json)) : {};
            var ps = String(json.payment_status || '').toLowerCase().trim();
            var approvePreMsg = ps === 'paid'
                ? 'Before you approve: confirm payment is complete (this row shows Paid) and that country, site URL, and database credentials are correct. Open the approval form?'
                : 'Before you approve: this row is not marked Paid. If your policy is payment before go-live, use Mark Paid or Edit the request first. You may still approve to create the agency. Open the approval form?';
            showConfirm(approvePreMsg).then(function(ok) {
                if (!ok) return;
                openApproveModal(json);
            });
            return;
        }
        var btnDelete = e.target.closest('.btn-delete');
        if (btnDelete) {
            e.preventDefault();
            e.stopPropagation();
            var id = btnDelete.dataset.id;
            showConfirm('Delete this registration request? This cannot be undone.').then(function(ok) {
                if (!ok) return;
                api('/registration-requests.php', 'DELETE', { ids: [parseInt(id, 10)] }).then(function(r) {
                    if (r.success) location.reload(); else showAlert(r.message);
                }).catch(function(e) { showAlert('Request failed: ' + (e.message || e)); });
            });
            return;
        }
        var btnReject = e.target.closest('.btn-reject');
        if (btnReject) {
            e.preventDefault();
            e.stopPropagation();
            var id = btnReject.dataset.id;
            showConfirm('Reject this registration request?').then(function(ok) {
                if (!ok) return;
                api('/registration-requests.php', 'PATCH', { id: parseInt(id, 10), action: 'reject' }).then(function(r) {
                    if (r.success) location.reload(); else showAlert(r.message);
                }).catch(function(e) { showAlert('Request failed: ' + (e.message || e)); });
            });
            return;
        }
        var btnMarkPaid = e.target.closest('.btn-mark-paid');
        if (btnMarkPaid) {
            e.preventDefault();
            e.stopPropagation();
            var id = btnMarkPaid.dataset.id;
            var amountStr = (btnMarkPaid.dataset.amount || '').trim();
            var amount = amountStr ? parseFloat(amountStr) : NaN;
            if (!amountStr || !isFinite(amount) || amount <= 0) {
                showAlert('Please set a valid plan amount first (edit the request).');
                return;
            }
            showConfirm('Mark this registration as PAID for $' + amount.toFixed(2) + '?').then(function(ok) {
                if (!ok) return;
                api('/registration-requests.php', 'PUT', {
                    id: parseInt(id, 10),
                    plan_amount: amount,
                    payment_status: 'paid',
                    payment_method: 'register'
                }).then(function(r) {
                    if (r.success) {
                        var row = btnMarkPaid.closest('tr');
                        if (row) {
                            row.setAttribute('data-payment-status', 'paid');
                            var payCell = row.querySelector('td.req-col-payment');
                            if (payCell) payCell.innerHTML = '<span class="badge badge-success">Paid (register)</span>';
                            btnMarkPaid.remove();
                            flashRows([row], 'req-row-updated-paid');
                        }
                        showAlert('Marked as paid.');
                    } else showAlert(r.message);
                }).catch(function(err) {
                    showAlert('Request failed: ' + (err.message || err));
                });
            });
        }
    }, true);

    var btnEditFromView = document.getElementById('btnEditFromView');
    if (btnEditFromView) btnEditFromView.onclick = function() {
        if (!viewModalRowData) return;
        if (viewModal) viewModal.hide();
        openEditModal(viewModalRowData);
    };

    var btnEditSave = document.getElementById('btnEditSave');
    if (btnEditSave) btnEditSave.onclick = function() {
        var id = document.getElementById('editId').value;
        var agencyName = document.getElementById('editAgencyName').value.trim();
        var contactEmail = document.getElementById('editContactEmail').value.trim();
        if (!agencyName) { showAlert('Agency name is required'); return; }
        if (!contactEmail) { showAlert('Contact email is required'); return; }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(contactEmail)) { showAlert('Invalid email address'); return; }
        var siteUrl = document.getElementById('editSiteUrl').value.trim();
        if (siteUrl && !/^https?:\/\/.+/.test(siteUrl)) { showAlert('Site URL must start with http:// or https://'); return; }
        var planSel = document.getElementById('editPlan');
        var plan = planSel ? (planSel.value || '') : '';
        var planAmountRaw = document.getElementById('editPlanAmount') ? toWesternNum(document.getElementById('editPlanAmount').value.trim()) : '';
        var yearsRaw = document.getElementById('editYears') ? toWesternNum(document.getElementById('editYears').value.trim()) : '';
        var payStatusSel = document.getElementById('editPaymentStatus');
        var payStatus = payStatusSel ? (payStatusSel.value || '') : '';
        var payMethodSel = document.getElementById('editPaymentMethod');
        var payMethod = payMethodSel ? (payMethodSel.value || '') : '';
        if (planAmountRaw && isNaN(parseFloat(planAmountRaw))) { showAlert('Plan amount must be a number'); return; }
        if (yearsRaw && isNaN(parseInt(yearsRaw, 10))) { showAlert('Duration (years) must be a number'); return; }
        var editAgencyIdEl = document.getElementById('editAgencyId');
        var payload = {
            id: parseInt(id, 10),
            agency_name: agencyName,
            agency_id: editAgencyIdEl ? editAgencyIdEl.value.trim() : '',
            country_name: document.getElementById('editCountryName').value.trim(),
            contact_email: contactEmail,
            contact_phone: document.getElementById('editContactPhone').value.trim(),
            desired_site_url: siteUrl,
            notes: document.getElementById('editNotes').value.trim()
        };
        if (plan) payload.plan = plan;
        if (planAmountRaw !== '') payload.plan_amount = parseFloat(planAmountRaw);
        if (yearsRaw !== '') payload.years = parseInt(yearsRaw, 10);
        if (payStatus) payload.payment_status = payStatus;
        if (payMethod) payload.payment_method = payMethod;
        var btn = this;
        btn.disabled = true;
        api('/registration-requests.php', 'PUT', payload).then(function(res) {
            if (res.success) { if (editModal) editModal.hide(); location.reload(); }
            else showAlert(res.message);
        }).catch(function(e) { showAlert('Request failed: ' + (e.message || e)); }).finally(function() { btn.disabled = false; });
    };

    var btnApproveSubmit = document.getElementById('btnApproveSubmit');
    if (btnApproveSubmit) btnApproveSubmit.onclick = function() {
        var reqId = document.getElementById('approveRequestId').value;
        var countryId = document.getElementById('approveCountryId').value;
        var name = document.getElementById('approveName').value.trim();
        var slug = document.getElementById('approveSlug').value.trim();
        var siteUrl = document.getElementById('approveSiteUrl').value.trim();
        var dbHost = document.getElementById('approveDbHost').value.trim() || 'localhost';
        var dbPort = parseInt(toWesternNum(document.getElementById('approveDbPort').value), 10) || 3306;
        var dbUser = document.getElementById('approveDbUser').value.trim();
        var dbPass = document.getElementById('approveDbPass').value;
        var dbName = document.getElementById('approveDbName').value.trim();

        var missing = [];
        if (!name) missing.push('Name');
        if (!(countryId ? parseInt(countryId, 10) : 0)) missing.push('Country');
        if (!siteUrl) missing.push('Site URL');
        if (!dbUser) missing.push('DB User');
        if (!dbPass) missing.push('DB Password');
        if (!dbName) missing.push('DB Name');
        if (missing.length) { showAlert('Missing: ' + missing.join(', ')); return; }
        if (dbPort < 1 || dbPort > 65535) { showAlert('DB Port must be 1-65535'); return; }
        if (slug && !/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(slug)) { showAlert('Slug: lowercase letters, numbers, hyphens only'); return; }
        if (siteUrl && !/^https?:\/\/.+/.test(siteUrl)) { showAlert('Site URL must start with http:// or https://'); return; }

        var payload = { country_id: parseInt(countryId, 10), name: name, slug: slug || null, site_url: siteUrl, db_host: dbHost, db_port: dbPort, db_user: dbUser, db_pass: dbPass, db_name: dbName, is_active: 1 };
        var btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Creating...';

        api('/agencies.php', 'POST', payload).then(function(r) {
            if (!r.success) throw new Error(r.message);
            return api('/registration-requests.php', 'PATCH', { id: parseInt(reqId, 10), action: 'approve', agency_id: r.id });
        }).then(function(r) {
            if (r.success) { if (approveModal) approveModal.hide(); location.reload(); }
            else showAlert(r.message);
        }).catch(function(e) { showAlert('Error: ' + (e.message || e)); })
        .finally(function() { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check me-1"></i> Create Agency & Approve'; });
    };

    // Bulk selection controls
    var reqCheckAll = document.getElementById('reqCheckAll');
    if (reqCheckAll) reqCheckAll.addEventListener('change', function() {
        var checked = !!this.checked;
        document.querySelectorAll('.req-row-check').forEach(function(chk) { chk.checked = checked; });
        updateSelectedCount();
    });
    document.querySelectorAll('.req-row-check').forEach(function(chk) {
        chk.addEventListener('change', updateSelectedCount);
    });
    var btnSelectAllRows = document.getElementById('btnSelectAllRows');
    if (btnSelectAllRows) btnSelectAllRows.onclick = function() {
        document.querySelectorAll('.req-row-check').forEach(function(chk) { chk.checked = true; });
        updateSelectedCount();
    };
    var btnClearSelectedRows = document.getElementById('btnClearSelectedRows');
    if (btnClearSelectedRows) btnClearSelectedRows.onclick = function() {
        document.querySelectorAll('.req-row-check').forEach(function(chk) { chk.checked = false; });
        updateSelectedCount();
    };
    updateSelectedCount();
    refreshAllRegistrationRowVisuals();

    // Bulk request actions
    var btnBulkDelete = document.getElementById('btnBulkDelete');
    if (btnBulkDelete) btnBulkDelete.onclick = function() {
        var ids = requireSelection();
        if (!ids) return;
        var rows = getSelectedRows();
        showConfirm('Delete ' + ids.length + ' selected request(s)? This cannot be undone.').then(function(ok) {
            if (!ok) return;
            api('/registration-requests.php', 'DELETE', { ids: ids }).then(function(r) {
                if (r.success) {
                    rows.forEach(function(row) { row.remove(); });
                    clearSelection();
                    showAlert('Deleted ' + ids.length + ' request(s).');
                } else showAlert(r.message || 'Delete failed');
            }).catch(function(e) { showAlert('Request failed: ' + (e.message || e)); });
        });
    };
    var btnBulkReject = document.getElementById('btnBulkReject');
    if (btnBulkReject) btnBulkReject.onclick = function() {
        var rows = getSelectedRows().filter(function(row) { return (row.getAttribute('data-status') || '') === 'pending'; });
        if (!rows.length) { showAlert('Select at least one pending request.'); return; }
        showConfirm('Reject ' + rows.length + ' pending request(s)?').then(function(ok) {
            if (!ok) return;
            Promise.all(rows.map(function(row) {
                return api('/registration-requests.php', 'PATCH', { id: parseInt(row.getAttribute('data-id'), 10), action: 'reject' });
            })).then(function() {
                rows.forEach(function(row) {
                    row.setAttribute('data-status', 'rejected');
                    var statusCell = row.querySelector('td.req-col-status .badge');
                    if (statusCell) {
                        statusCell.className = 'badge badge-rejected';
                        statusCell.textContent = 'Rejected';
                    }
                    applyRegistrationRowVisual(row);
                });
                flashRows(rows, 'req-row-updated-rejected');
                clearSelection();
                showAlert('Rejected ' + rows.length + ' request(s).');
            })
            .catch(function(e) { showAlert('Request failed: ' + (e.message || e)); });
        });
    };
    var btnBulkMarkPaid = document.getElementById('btnBulkMarkPaid');
    if (btnBulkMarkPaid) btnBulkMarkPaid.onclick = function() {
        var rows = getSelectedRows().filter(function(row) {
            var pay = (row.getAttribute('data-payment-status') || '').toLowerCase();
            var amount = parseFloat((row.getAttribute('data-plan-amount') || '').trim());
            return pay !== 'paid' && isFinite(amount) && amount > 0;
        });
        if (!rows.length) { showAlert('Select rows with valid amount and unpaid status.'); return; }
        showConfirm('Mark ' + rows.length + ' selected request(s) as paid?').then(function(ok) {
            if (!ok) return;
            Promise.all(rows.map(function(row) {
                var id = parseInt(row.getAttribute('data-id'), 10);
                var amount = parseFloat((row.getAttribute('data-plan-amount') || '').trim());
                return api('/registration-requests.php', 'PUT', { id: id, plan_amount: amount, payment_status: 'paid', payment_method: 'register' });
            })).then(function() {
                rows.forEach(function(row) {
                    row.setAttribute('data-payment-status', 'paid');
                    var payCell = row.querySelector('td.req-col-payment');
                    if (payCell) payCell.innerHTML = '<span class="badge badge-success">Paid (register)</span>';
                    var btn = row.querySelector('.btn-mark-paid');
                    if (btn) btn.remove();
                });
                flashRows(rows, 'req-row-updated-paid');
                clearSelection();
                showAlert('Marked paid for ' + rows.length + ' request(s).');
            })
            .catch(function(e) { showAlert('Request failed: ' + (e.message || e)); });
        });
    };

    // Bulk agency actions for created agencies referenced by selected rows
    function runBulkAgencyPatch(payload, actionLabel) {
        var directIds = getSelectedAgencyIds();
        var unresolvedNames = getSelectedAgencyNamesWithoutCreatedId();
        resolveAgencyIdsByNames(unresolvedNames).then(function(resolvedIds) {
            var all = {};
            directIds.concat(resolvedIds).forEach(function(id) { if (id > 0) all[id] = true; });
            var agencyIds = Object.keys(all).map(function(k) { return parseInt(k, 10); }).filter(function(x) { return x > 0; });
            if (!agencyIds.length) {
                showAlert('No linked agency found in selected rows. Use rows with Created Agency or agency names that exist in Agencies page.');
                return;
            }
            showConfirm(actionLabel + ' for ' + agencyIds.length + ' agency(s)?').then(function(ok) {
                if (!ok) return;
                var req = Object.assign({ ids: agencyIds }, payload || {});
                api('/agencies.php', 'PATCH', req).then(function(r) {
                    if (r.success) {
                        var idSet = {};
                        agencyIds.forEach(function(id) { idSet[id] = true; });
                        document.querySelectorAll('.req-table tbody tr[data-id]').forEach(function(row) {
                            var aid = parseInt(row.getAttribute('data-created-agency-id') || '0', 10);
                            if (aid <= 0 || !idSet[aid]) return;
                            if (payload && Object.prototype.hasOwnProperty.call(payload, 'is_active')) {
                                row.setAttribute('data-agency-is-active', payload.is_active === 1 ? '1' : '0');
                            }
                            if (payload && Object.prototype.hasOwnProperty.call(payload, 'is_suspended')) {
                                row.setAttribute('data-agency-suspended', payload.is_suspended === 1 ? '1' : '0');
                            }
                            applyRegistrationRowVisual(row);
                        });
                        var rows = getSelectedRows();
                        flashRows(rows, (payload && payload.is_active === 1) ? 'req-row-updated-active' : (payload && payload.is_active === 0) ? 'req-row-updated-warning' : (payload && payload.is_suspended === 1) ? 'req-row-updated-warning' : 'req-row-updated-active');
                        clearSelection();
                        showAlert(actionLabel + ' completed for ' + agencyIds.length + ' agency(s).');
                    } else showAlert(r.message || 'Bulk action failed');
                }).catch(function(e) { showAlert('Request failed: ' + (e.message || e)); });
            });
        });
    }
    var btnBulkSuspendAgency = document.getElementById('btnBulkSuspendAgency');
    if (btnBulkSuspendAgency) btnBulkSuspendAgency.onclick = function() { runBulkAgencyPatch({ is_suspended: 1 }, 'Suspend'); };
    var btnBulkUnsuspendAgency = document.getElementById('btnBulkUnsuspendAgency');
    if (btnBulkUnsuspendAgency) btnBulkUnsuspendAgency.onclick = function() { runBulkAgencyPatch({ is_suspended: 0 }, 'Unsuspend'); };
    var btnBulkDeactivateAgency = document.getElementById('btnBulkDeactivateAgency');
    if (btnBulkDeactivateAgency) btnBulkDeactivateAgency.onclick = function() { runBulkAgencyPatch({ is_active: 0 }, 'Deactivate'); };
    var btnBulkActivateAgency = document.getElementById('btnBulkActivateAgency');
    if (btnBulkActivateAgency) btnBulkActivateAgency.onclick = function() { runBulkAgencyPatch({ is_active: 1 }, 'Activate'); };

    /* Rows-per-page: submit owning form, or rebuild URL if select is ever outside a form (defensive). */
    document.querySelectorAll('.js-cp-reg-page-limit, #reqLimitSelectEmb').forEach(function(sel) {
        if (!sel || sel.tagName !== 'SELECT') {
            return;
        }
        sel.addEventListener('change', function() {
            var form = this.closest('form');
            if (form) {
                form.submit();
                return;
            }
            var lim = Math.max(5, Math.min(100, parseInt(String(this.value), 10) || 10));
            try {
                var u = new URL(window.location.href);
                u.searchParams.set('limit', String(lim));
                u.searchParams.delete('page');
                window.location.assign(u.href);
            } catch (e) {
                window.location.reload();
            }
        });
    });
})();
