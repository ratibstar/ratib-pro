/**
 * EN: Implements control-panel module behavior and admin-country operations in `control-panel/js/accounting-modals.js`.
 * AR: ينفذ سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/js/accounting-modals.js`.
 */
/**
 * Control Panel – accounting modals (loaded after Flatpickr + full DOM).
 * Bootstrap globals from JSON in hidden #cp-acc-bootstrap-* elements (accounting-modals.php).
 */
(function () {
    function parseJsonEl(id) {
        var el = document.getElementById(id);
        if (!el || !el.textContent) return null;
        try { return JSON.parse(el.textContent.trim()); } catch (e) { return null; }
    }
    var titles = parseJsonEl('cp-acc-bootstrap-report-titles');
    if (titles && typeof titles === 'object') window.cpAccReportTitles = titles;
    var acct = parseJsonEl('cp-acc-bootstrap-chart-accounts');
    if (Array.isArray(acct)) window.cpAccChartAccountsForJournal = acct;
    var cc = parseJsonEl('cp-acc-bootstrap-cost-centers');
    if (Array.isArray(cc)) window.cpAccCostCentersForJournal = cc;
})();
(function(){
    var AR_NUM = {'٠':'0','١':'1','٢':'2','٣':'3','٤':'4','٥':'5','٦':'6','٧':'7','٨':'8','٩':'9','۰':'0','۱':'1','۲':'2','۳':'3','۴':'4','۵':'5','۶':'6','۷':'7','۸':'8','۹':'9'};
    window.cpAccNormalizeWesternNumberString = function(s) {
        if (s == null) return '';
        var str = String(s).replace(/,/g, '').replace(/\s/g, '');
        var out = '';
        for (var i = 0; i < str.length; i++) {
            var ch = str.charAt(i);
            out += AR_NUM.hasOwnProperty(ch) ? AR_NUM[ch] : ch;
        }
        return out;
    };
    window.cpAccParseDecimal = function(s) {
        var n = parseFloat(window.cpAccNormalizeWesternNumberString(s));
        return isNaN(n) ? 0 : n;
    };
    window.cpAccSanitizeAmountTyping = function(el) {
        if (!el) return;
        var raw = window.cpAccNormalizeWesternNumberString(el.value).replace(/[^\d.]/g, '');
        var dot = raw.indexOf('.');
        if (dot !== -1) raw = raw.slice(0, dot + 1) + raw.slice(dot + 1).replace(/\./g, '');
        if (raw !== el.value) el.value = raw;
    };
    window.cpAccFormatAmountBlur = function(el) {
        if (!el) return;
        el.value = window.cpAccParseDecimal(el.value).toFixed(2);
    };
    /** Force YYYY-MM-DD (ASCII hyphens) in the field — avoids RTL/bidi or locale oddities in Financial Reports and elsewhere. */
    window.cpAccSetFpInputYmd = function(instance, selectedDates) {
        var inp = instance && instance.input;
        if (!inp) return;
        if (!selectedDates || !selectedDates.length) {
            inp.value = '';
            return;
        }
        var d = selectedDates[0];
        inp.value = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    };
})();

(function(){
    if (window.cpAccFpLocaleEn) return;
    window.cpAccFpLocaleEn = {
        weekdays: { shorthand: ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'], longhand: ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] },
        months: { shorthand: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'], longhand: ['January','February','March','April','May','June','July','August','September','October','November','December'] },
        firstDayOfWeek: 0,
        rangeSeparator: ' to ',
        weekAbbreviation: 'Wk'
    };
    window.cpAccBindEnglishFlatpickr = function(root) {
        root = root || document;
        if (typeof flatpickr === 'undefined') return;
        try { if (flatpickr.localize) flatpickr.localize(window.cpAccFpLocaleEn); } catch (e) {}
        var loc = window.cpAccFpLocaleEn;
        root.querySelectorAll('input.cp-acc-fp-en:not([data-cp-acc-fp-bound])').forEach(function(inp) {
            inp.setAttribute('data-cp-acc-fp-bound', '1');
            var initial = (inp.value || '').trim();
            flatpickr(inp, {
                locale: loc,
                dateFormat: 'Y-m-d',
                allowInput: false,
                disableMobile: true,
                clickOpens: true,
                defaultDate: initial || undefined,
                onReady: function(selectedDates, _s, inst) {
                    if (selectedDates && selectedDates.length) window.cpAccSetFpInputYmd(inst, selectedDates);
                },
                onChange: function(selectedDates, _s, inst) {
                    window.cpAccSetFpInputYmd(inst, selectedDates || []);
                }
            });
        });
    };
    var cpAccFpLoadTries = 0;
    function cpAccScheduleFpInit() {
        if (typeof flatpickr === 'undefined') {
            cpAccFpLoadTries++;
            if (cpAccFpLoadTries < 100) {
                setTimeout(cpAccScheduleFpInit, 80);
                return;
            }
            document.querySelectorAll('input.cp-acc-fp-en').forEach(function(inp) {
                if (!inp._flatpickr) inp.removeAttribute('readonly');
            });
            return;
        }
        window.cpAccBindEnglishFlatpickr(document);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', cpAccScheduleFpInit);
    else cpAccScheduleFpInit();
    window.addEventListener('load', function() { window.cpAccBindEnglishFlatpickr && window.cpAccBindEnglishFlatpickr(document); });
})();

(function(){
    /** Resolve control accounting API base — must match this tab's origin so session cookies are sent (avoids "Unauthorized"). */
    function cpAccControlApiBase() {
        var b = '';
        try {
            var p = window.location.pathname || '';
            var low = p.toLowerCase();
            var idx = low.indexOf('/control-panel');
            if (idx !== -1) {
                b = window.location.origin + p.slice(0, idx + '/control-panel'.length) + '/api/control';
            }
        } catch (e0) {
            b = '';
        }
        b = String(b || '').trim().replace(/\/$/, '');
        if (!b) {
            var el = document.getElementById('control-config')
                || document.getElementById('accountingContent')
                || document.getElementById('app-config');
            if (el) {
                b = el.getAttribute('data-api-base') || (el.dataset && el.dataset.apiBase) || '';
                if (!b && el.id === 'app-config') {
                    b = el.getAttribute('data-control-api-path') || '';
                }
            }
            if (!b && typeof window.APP_CONFIG !== 'undefined' && window.APP_CONFIG.controlApiPath) {
                b = window.APP_CONFIG.controlApiPath;
            }
            b = String(b || '').trim().replace(/\/$/, '');
        }
        if (!b) {
            try {
                b = window.location.origin + '/api/control';
            } catch (e1) {
                b = '';
            }
        }
        return b;
    }
    function cpAccAccountingApiUrl(path) {
        path = path.charAt(0) === '/' ? path : '/' + path;
        var url = cpAccControlApiBase() + path;
        return url.indexOf('?') === -1 ? url + '?control=1' : url + '&control=1';
    }
    function cpAccCsrfToken() {
        var cfg = document.getElementById('accountingContent') || document.getElementById('control-config');
        if (!cfg) return '';
        return String(cfg.getAttribute('data-csrf-token') || cfg.dataset.csrfToken || '').trim();
    }
    function cpAccWithCsrfHeaders(headers) {
        var out = headers || {};
        var token = cpAccCsrfToken();
        if (token) out['X-CSRF-Token'] = token;
        return out;
    }
    function cpAccAccountingResponseJson(r) {
        return r.text().then(function(t) {
            try {
                return JSON.parse(t);
            } catch (e) {
                var msg = (r.status === 401 || (t && /Unauthorized/i.test(String(t)))) ? 'Session expired — refresh the page and sign in again.' : 'Invalid server response.';
                return { success: false, message: msg };
            }
        });
    }
    function cpAccEscapeHtml(v) {
        if (v == null) return '';
        var d = document.createElement('div');
        d.textContent = String(v);
        return d.innerHTML;
    }
    function cpAccFormatGlReference(ref) {
        ref = String(ref || '').trim();
        var m = ref.match(/^GL-\d{4}-(\d+)$/i);
        if (m) return 'GL-' + String(m[1]).padStart(5, '0');
        return ref;
    }
    function cpAccCanManageAccounting() {
        var el = document.getElementById('accountingContent');
        return !!(el && el.getAttribute('data-can-manage') === '1');
    }
    function cpAccLockReadOnlyUi() {
        if (cpAccCanManageAccounting()) return;
        var selectors = [
            '#chartNewAccountBtn', '#chartBulkDeleteBtn', '.chart-delete-btn', '#chartAccountFormSaveBtn',
            '#costModalAddBtn', '#costCenterFormSaveBtn', '#bankModalAddBtn', '#bankGuaranteeFormSaveBtn',
            '#cpAccSupportNewBtn', '#cpAccSupportBulkDelete', '.cp-acc-support-delete', '#cpAccSupportFormSaveBtn',
            '#cpAccLedgerBulkDelete', '.cp-acc-ledger-delete', '#cpAccNormalizeNumbersBtn',
            '#cpAccExpenseNewBtn', '#cpAccExpenseBulkDelete', '.cp-acc-expense-delete', '#cpAccExpenseFormSaveBtn',
            '#cpAccReceiptBulkDelete', '.cp-acc-receipt-delete', '#cpAccRcJeSaveBtn',
            '.cp-acc-open-generic-form', '#cpAccBulkApproveApprovals', '#cpAccBulkRejectApprovals', '#cpAccBulkUndoApprovals',
            '.cp-acc-approval-approve', '.cp-acc-approval-reject', '.cp-acc-approval-undo',
            '#cpAccRejectReasonConfirmBtn'
        ];
        document.querySelectorAll(selectors.join(',')).forEach(function(el) {
            el.style.display = 'none';
        });
        document.addEventListener('click', function(e) {
            var blocked = e.target && e.target.closest && e.target.closest(selectors.join(','));
            if (blocked) {
                e.preventDefault();
                e.stopPropagation();
                if (window.cpAccShowToast) window.cpAccShowToast('Read-only access: manage permission required.');
            }
        }, true);
    }
    function closeModal(modal) {
        if (modal && modal.id === 'ledgerJournalEditModal') {
            var jeDate = document.getElementById('cpAccJeEditDate');
            if (jeDate && jeDate._flatpickr) jeDate._flatpickr.destroy();
        }
        if (modal && modal.id === 'newJournalModal') {
            var njDate = document.getElementById('cpAccNewJeDate');
            if (njDate && njDate._flatpickr) njDate._flatpickr.destroy();
        }
        if (modal && modal.id === 'cpAccReceiptJournalModal') {
            var rcDate = document.getElementById('cpAccRcJeDate');
            if (rcDate && rcDate._flatpickr) rcDate._flatpickr.destroy();
        }
        if (modal && modal.id === 'cpAccExpenseFormModal') {
            var exDate = document.getElementById('cpAccExJeDate');
            if (exDate && exDate._flatpickr) exDate._flatpickr.destroy();
        }
        if (modal && modal.id === 'cpAccSupportPaymentFormModal') {
            var spDate = document.getElementById('cpAccSupportFormDate');
            if (spDate && spDate._flatpickr) spDate._flatpickr.destroy();
        }
        if (modal) modal.classList.remove('is-open');
        try {
            if (modal && modal.id && typeof location !== 'undefined' && location.hash === '#' + modal.id) {
                history.replaceState(null, '', location.pathname + location.search);
            }
        } catch (eHash) {}
        var anyOpen = document.querySelector('.cp-acc-modal.is-open');
        var hashId = '';
        try { hashId = (location.hash || '').replace(/^#/, ''); } catch (eH) {}
        var hashModal = hashId ? document.getElementById(hashId) : null;
        var hashOpen = hashModal && hashModal.classList && hashModal.classList.contains('cp-acc-modal');
        document.body.style.overflow = (anyOpen || hashOpen) ? 'hidden' : '';
    }
    function reopenModalIfFlagged(id, key) {
        // Only reopen when the URL hash explicitly requests it.
        // This avoids getting stuck in an "open modal overlay" state due to stale sessionStorage flags.
        var hashOpen = (typeof location !== 'undefined' && location.hash === '#' + id);
        if (!hashOpen) return;
        try { sessionStorage.removeItem(key); } catch (e) {}
        var m = document.getElementById(id);
        if (m) {
            m.classList.add('is-open');
            document.body.style.overflow = 'hidden';
            if (window.cpAccBindEnglishFlatpickr) window.cpAccBindEnglishFlatpickr(m);
        }
        // Clear hash after is-open so :target and .is-open do not fight; modal stays open via class.
        try { history.replaceState(null, '', location.pathname + location.search); } catch (e) {}
    }
    window.cpAccReloadAndReopenModal = function(storageKey) {
        var idMap = {
            cpAccOpenChartModal: 'chartModal',
            cpAccOpenCostModal: 'costModal',
            cpAccOpenBankModal: 'bankModal',
            cpAccOpenApprovalModal: 'approvalModal',
            cpAccOpenLedgerModal: 'ledgerModal',
            cpAccOpenNewJournalModal: 'newJournalModal',
            cpAccOpenExpensesModal: 'expensesModal',
            cpAccOpenReceiptsModal: 'receiptsModal',
            cpAccOpenSupportModal: 'supportModal',
            cpAccOpenVouchersModal: 'vouchersModal',
            cpAccOpenInvoicesModal: 'invoicesModal',
            cpAccOpenReconcileModal: 'reconcileModal',
            cpAccOpenReportsModal: 'reportsModal'
        };
        var id = idMap[storageKey] || '';
        try { sessionStorage.setItem(storageKey, '1'); } catch (e) {}
        if (id) location.hash = id;
        location.reload();
    };
    function runReopen() {
        reopenModalIfFlagged('chartModal', 'cpAccOpenChartModal');
        reopenModalIfFlagged('costModal', 'cpAccOpenCostModal');
        reopenModalIfFlagged('bankModal', 'cpAccOpenBankModal');
        reopenModalIfFlagged('approvalModal', 'cpAccOpenApprovalModal');
        reopenModalIfFlagged('ledgerModal', 'cpAccOpenLedgerModal');
        reopenModalIfFlagged('newJournalModal', 'cpAccOpenNewJournalModal');
        reopenModalIfFlagged('expensesModal', 'cpAccOpenExpensesModal');
        reopenModalIfFlagged('receiptsModal', 'cpAccOpenReceiptsModal');
        reopenModalIfFlagged('supportModal', 'cpAccOpenSupportModal');
        reopenModalIfFlagged('vouchersModal', 'cpAccOpenVouchersModal');
        reopenModalIfFlagged('invoicesModal', 'cpAccOpenInvoicesModal');
        reopenModalIfFlagged('reconcileModal', 'cpAccOpenReconcileModal');
        reopenModalIfFlagged('reportsModal', 'cpAccOpenReportsModal');
        cpAccLockReadOnlyUi();
    }
    // Safety: prevent any leftover `.is-open` state from blocking UI.
    try {
        document.querySelectorAll('.cp-acc-modal.is-open').forEach(function(m){ closeModal(m); });
        document.body.style.overflow = '';
    } catch (e) {}
    runReopen();
    setTimeout(runReopen, 80);
    if (document.readyState !== 'complete') window.addEventListener('load', runReopen);
    document.addEventListener('click', function(e){
        var el = e.target && e.target.closest && e.target.closest('[data-cp-acc-modal]');
        if (!el) return;
        e.preventDefault();
        var id = el.getAttribute('data-cp-acc-modal');
        if (!id) return;
        var modal = document.getElementById(id);
        if (modal) {
            document.querySelectorAll('.cp-acc-modal.is-open').forEach(function(m){
                closeModal(m);
            });
            modal.classList.add('is-open');
            document.body.style.overflow = 'hidden';
            if (window.cpAccBindEnglishFlatpickr) window.cpAccBindEnglishFlatpickr(modal);
        }
    });

    // If an accounting modal is open, allow navigation without manual close.
    // When the user clicks the modal backdrop, we close the modal(s) and then
    // trigger the underlying nav element at the same coordinates.
    var cpAccNavRerouted = false;
    document.addEventListener('click', function(e){
        if (cpAccNavRerouted) { cpAccNavRerouted = false; return; }
        var openModals = document.querySelectorAll('.cp-acc-modal.is-open');
        if (!openModals || !openModals.length) return;
        if (!e || !e.target || !e.target.classList) return;
        if (!e.target.classList.contains('cp-acc-modal')) return; // only if clicking the backdrop
        var x = e.clientX;
        var y = e.clientY;
        openModals.forEach(function(m){ closeModal(m); });
        // Now that modals are closed, locate what's under the click.
        var el = (document.elementFromPoint && typeof document.elementFromPoint === 'function') ? document.elementFromPoint(x, y) : null;
        var link = el && el.closest ? el.closest('a.top-nav-link, a.quick-action-btn, a[data-cp-acc-modal]') : null;
        if (link && typeof link.click === 'function') {
            cpAccNavRerouted = true;
            link.click();
        }
    }, true);
    document.querySelectorAll('.cp-acc-modal').forEach(function(modal){
        modal.addEventListener('click', function(e){
            if (e.target === modal) closeModal(modal);
        });
    });
    document.querySelectorAll('.cp-acc-close').forEach(function(btn){
        btn.addEventListener('click', function(){
            var modal = btn.closest('.cp-acc-modal');
            closeModal(modal);
        });
    });
    document.querySelectorAll('.cp-acc-modal-cancel').forEach(function(btn){
        btn.addEventListener('click', function(){
            var modal = btn.closest('.cp-acc-modal');
            if (modal) closeModal(modal);
        });
    });
    var cpAccChartModalReloadBtn = document.getElementById('cpAccChartModalReloadBtn');
    if (cpAccChartModalReloadBtn) {
        cpAccChartModalReloadBtn.addEventListener('click', function(){ location.reload(); });
    }
    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape') {
            document.querySelectorAll('.cp-acc-modal.is-open').forEach(closeModal);
            try {
                var hid = (location.hash || '').replace(/^#/, '');
                var hel = hid ? document.getElementById(hid) : null;
                if (hel && hel.classList && hel.classList.contains('cp-acc-modal')) {
                    history.replaceState(null, '', location.pathname + location.search);
                }
            } catch (eEsc) {}
        }
    });

    (function initEntryApprovals(){
        var approvalModal = document.getElementById('approvalModal');
        var viewModal = document.getElementById('approvalJournalViewModal');
        var viewBody = document.getElementById('approvalJournalViewBody');
        if (!approvalModal) return;
        function cpAccApprovalGetSelectedIds() {
            var ids = [];
            approvalModal.querySelectorAll('.cp-acc-approval-cb:checked').forEach(function(cb){
                var v = parseInt(cb.value, 10);
                if (v) ids.push(v);
            });
            return ids;
        }
        function cpAccApprovalGetSelectedIdsByStatus(allowedStatuses) {
            var ids = [];
            var allowed = {};
            (allowedStatuses || []).forEach(function(s) { allowed[String(s || '').toLowerCase()] = true; });
            approvalModal.querySelectorAll('tr.cp-acc-approval-row').forEach(function(tr) {
                var cb = tr.querySelector('.cp-acc-approval-cb');
                if (!cb || !cb.checked) return;
                var st = String(tr.getAttribute('data-status') || '').toLowerCase();
                if (!allowed[st]) return;
                var v = parseInt(cb.value, 10);
                if (v) ids.push(v);
            });
            return ids;
        }
        function cpAccApprovalUpdateSelectionInfo() {
            var el = document.getElementById('cpAccApprovalSelectionInfo');
            if (el) el.textContent = cpAccApprovalGetSelectedIds().length + ' selected';
        }
        function cpAccApprovalApplyFilters() {
            var stEl = document.getElementById('cpAccApprovalFilterStatus');
            var status = stEl ? (stEl.value || '') : '';
            var df = (document.getElementById('cpAccApprovalDateFrom') || {}).value || '';
            var dt = (document.getElementById('cpAccApprovalDateTo') || {}).value || '';
            var lim = parseInt((document.getElementById('cpAccApprovalPageSize') || {}).value, 10);
            if (!lim || lim < 1) lim = 10;
            var shown = 0;
            approvalModal.querySelectorAll('tr.cp-acc-approval-row').forEach(function(tr){
                var st = (tr.getAttribute('data-status') || '').toLowerCase();
                var ed = tr.getAttribute('data-entry-date') || '';
                var ok = true;
                if (status && st !== status) ok = false;
                if (ok && df && ed && ed < df) ok = false;
                if (ok && dt && ed && ed > dt) ok = false;
                if (!ok) {
                    tr.style.display = 'none';
                    return;
                }
                shown++;
                tr.style.display = (shown <= lim) ? '' : 'none';
            });
            var selAll = document.getElementById('cpAccApprovalSelectAll');
            if (selAll) selAll.checked = false;
            approvalModal.querySelectorAll('.cp-acc-approval-cb').forEach(function(cb){ cb.checked = false; });
            cpAccApprovalUpdateSelectionInfo();
        }
        function cpAccEntryApprovalsMutate(op, ids, rejectReason) {
            if (!ids.length) { alert('No entries selected.'); return Promise.resolve(null); }
            var payload = { op: op, ids: ids };
            if (op === 'reject') payload.reject_reason = rejectReason || '';
            return fetch(cpAccAccountingApiUrl('/accounting.php?action=entry_approvals_mutate'), {
                method: 'POST',
                headers: cpAccWithCsrfHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify(payload),
                credentials: 'same-origin',
                cache: 'no-store'
            }).then(function(r){ return cpAccAccountingResponseJson(r); });
        }
        function cpAccAskRejectReason() {
            return new Promise(function(resolve) {
                var modal = document.getElementById('approvalRejectReasonModal');
                var sel = document.getElementById('cpAccRejectReasonSelect');
                var err = document.getElementById('cpAccRejectReasonError');
                var okBtn = document.getElementById('cpAccRejectReasonConfirmBtn');
                var cancelBtn = document.getElementById('cpAccRejectReasonCancelBtn');
                if (!modal || !sel || !okBtn || !cancelBtn) {
                    resolve('');
                    return;
                }
                sel.value = '';
                if (err) err.classList.add('d-none');
                modal.classList.add('is-open');
                document.body.style.overflow = 'hidden';
                function cleanup(v) {
                    modal.classList.remove('is-open');
                    var anyOpen = document.querySelector('.cp-acc-modal.is-open');
                    document.body.style.overflow = anyOpen ? 'hidden' : '';
                    okBtn.removeEventListener('click', onOk);
                    cancelBtn.removeEventListener('click', onCancel);
                    modal.removeEventListener('click', onBackdrop);
                    resolve(v);
                }
                function onOk() {
                    var v = String(sel.value || '').trim();
                    if (!v) {
                        if (err) err.classList.remove('d-none');
                        return;
                    }
                    if (err) err.classList.add('d-none');
                    cleanup(v);
                }
                function onCancel(e) {
                    if (e) e.preventDefault();
                    cleanup('');
                }
                function onBackdrop(e) {
                    if (e.target === modal || e.target.closest('.cp-acc-close')) {
                        cleanup('');
                    }
                }
                okBtn.addEventListener('click', onOk);
                cancelBtn.addEventListener('click', onCancel);
                modal.addEventListener('click', onBackdrop);
                sel.addEventListener('change', function() {
                    if (err && String(sel.value || '').trim()) err.classList.add('d-none');
                }, { once: true });
            });
        }
        function cpAccAfterApprovalsMutate(res) {
            if (res && res.success) {
                var updated = parseInt(res.approvals_updated, 10) || 0;
                var skipped = parseInt(res.skipped_count, 10) || 0;
                var selected = parseInt(res.selected_count, 10) || 0;
                if (selected > 1 || skipped > 0) {
                    alert((res.message || 'Done') + '. Updated: ' + updated + ', skipped: ' + skipped + '.');
                }
                window.cpAccReloadAndReopenModal('cpAccOpenApprovalModal');
            } else {
                alert((res && res.message) ? res.message : 'Request failed.');
            }
        }
        approvalModal.addEventListener('change', function(e){
            if (e.target && e.target.id === 'cpAccApprovalSelectAll') {
                var on = e.target.checked;
                approvalModal.querySelectorAll('tr.cp-acc-approval-row').forEach(function(tr){
                    if (tr.style.display === 'none') return;
                    var cb = tr.querySelector('.cp-acc-approval-cb');
                    if (cb) cb.checked = on;
                });
                cpAccApprovalUpdateSelectionInfo();
                return;
            }
            if (e.target && e.target.classList && e.target.classList.contains('cp-acc-approval-cb')) {
                cpAccApprovalUpdateSelectionInfo();
            }
        });
        ['cpAccApprovalFilterStatus', 'cpAccApprovalDateFrom', 'cpAccApprovalDateTo', 'cpAccApprovalPageSize'].forEach(function(id){
            var el = document.getElementById(id);
            if (el) el.addEventListener('change', cpAccApprovalApplyFilters);
        });
        approvalModal.addEventListener('click', function(e){
            var approveBtn = e.target.closest('.cp-acc-approval-approve');
            if (approveBtn) {
                e.preventDefault();
                var id = parseInt(approveBtn.getAttribute('data-approval-id'), 10);
                if (!id) return;
                if (!confirm('Approve this journal entry?')) return;
                cpAccEntryApprovalsMutate('approve', [id]).then(cpAccAfterApprovalsMutate);
                return;
            }
            var rejectBtn = e.target.closest('.cp-acc-approval-reject');
            if (rejectBtn) {
                e.preventDefault();
                var rid = parseInt(rejectBtn.getAttribute('data-approval-id'), 10);
                if (!rid) return;
                cpAccAskRejectReason().then(function(reason) {
                    if (!reason) return;
                    cpAccEntryApprovalsMutate('reject', [rid], reason).then(cpAccAfterApprovalsMutate);
                });
                return;
            }
            var undoBtn = e.target.closest('.cp-acc-approval-undo');
            if (undoBtn) {
                e.preventDefault();
                var uid = parseInt(undoBtn.getAttribute('data-approval-id'), 10);
                if (!uid) return;
                if (!confirm('Undo this decision and move it back to pending?')) return;
                cpAccEntryApprovalsMutate('undo', [uid]).then(cpAccAfterApprovalsMutate);
                return;
            }
            var viewBtn = e.target.closest('.cp-acc-approval-view');
            if (viewBtn && viewBody && viewModal) {
                e.preventDefault();
                var jid = parseInt(viewBtn.getAttribute('data-journal-id'), 10);
                if (!jid) return;
                viewBody.innerHTML = '<p class="text-muted mb-0">Loading…</p>';
                viewModal.classList.add('is-open');
                document.body.style.overflow = 'hidden';
                fetch(cpAccAccountingApiUrl('/accounting.php?action=journal_entry_detail&journal_entry_id=' + jid), { credentials: 'same-origin', cache: 'no-store' })
                    .then(function(r){ return cpAccAccountingResponseJson(r); })
                    .then(function(res){
                        if (!res || !res.success || !res.entry) {
                            viewBody.innerHTML = '<p class="text-danger">' + ((res && res.message) ? res.message : 'Failed to load') + '</p>';
                            return;
                        }
                        var je = res.entry;
                        var lines = res.lines || [];
                        var html = '<div class="mb-3"><div class="small text-muted">Reference</div><div class="fw-bold">' + cpAccEscapeHtml(cpAccFormatGlReference(je.reference || '—')) + '</div>';
                        html += '<div class="small text-muted mt-2">Date</div><div>' + cpAccEscapeHtml(je.entry_date || '—') + '</div>';
                        html += '<div class="small text-muted mt-2">Status</div><div>' + cpAccEscapeHtml(je.status || '—') + '</div>';
                        html += '<div class="small text-muted mt-2">Description</div><div>' + cpAccEscapeHtml(je.description || '—') + '</div>';
                        html += '<div class="row mt-2"><div class="col-6 small text-muted">Total debit</div><div class="col-6 small text-muted">Total credit</div>';
                        html += '<div class="col-6">' + (parseFloat(je.total_debit) || 0).toFixed(2) + '</div><div class="col-6">' + (parseFloat(je.total_credit) || 0).toFixed(2) + '</div></div></div>';
                        html += '<h6 class="mb-2">Lines</h6><div class="cp-acc-table-wrap"><table class="cp-acc-table"><thead><tr><th>Description</th><th>Debit</th><th>Credit</th></tr></thead><tbody>';
                        if (!lines.length) {
                            html += '<tr><td colspan="3" class="text-muted">No lines</td></tr>';
                        } else {
                            lines.forEach(function(L){
                                html += '<tr><td>' + cpAccEscapeHtml(L.description || '—') + '</td><td>' + (parseFloat(L.debit) || 0).toFixed(2) + '</td><td>' + (parseFloat(L.credit) || 0).toFixed(2) + '</td></tr>';
                            });
                        }
                        html += '</tbody></table></div>';
                        viewBody.innerHTML = html;
                    })
                    .catch(function(){ viewBody.innerHTML = '<p class="text-danger">Request failed.</p>'; });
                return;
            }
        });
        var bulkAp = document.getElementById('cpAccBulkApproveApprovals');
        var bulkRj = document.getElementById('cpAccBulkRejectApprovals');
        var bulkUd = document.getElementById('cpAccBulkUndoApprovals');
        if (bulkAp) bulkAp.addEventListener('click', function(){
            var ids = cpAccApprovalGetSelectedIdsByStatus(['pending']);
            if (!ids.length) { alert('Select one or more pending entries.'); return; }
            if (!confirm('Approve ' + ids.length + ' selected entr' + (ids.length === 1 ? 'y' : 'ies') + '?')) return;
            cpAccEntryApprovalsMutate('approve', ids).then(cpAccAfterApprovalsMutate);
        });
        if (bulkRj) bulkRj.addEventListener('click', function(){
            var ids = cpAccApprovalGetSelectedIdsByStatus(['pending']);
            if (!ids.length) { alert('Select one or more pending entries.'); return; }
            cpAccAskRejectReason().then(function(reason) {
                if (!reason) return;
                cpAccEntryApprovalsMutate('reject', ids, reason).then(cpAccAfterApprovalsMutate);
            });
        });
        if (bulkUd) bulkUd.addEventListener('click', function(){
            var ids = cpAccApprovalGetSelectedIdsByStatus(['approved', 'rejected']);
            if (!ids.length) { alert('Select one or more approved/rejected entries.'); return; }
            if (!confirm('Undo decision for ' + ids.length + ' selected entr' + (ids.length === 1 ? 'y' : 'ies') + '?')) return;
            cpAccEntryApprovalsMutate('undo', ids).then(cpAccAfterApprovalsMutate);
        });

        var fpLocaleApprovals = {
            weekdays: { shorthand: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'], longhand: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] },
            months: { shorthand: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'], longhand: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'] },
            firstDayOfWeek: 0,
            rangeSeparator: ' to ',
            weekAbbreviation: 'Wk'
        };
        var cpAccApprovalFpDone = false;
        function initCpAccApprovalFlatpickr() {
            if (cpAccApprovalFpDone) return;
            if (typeof flatpickr === 'undefined') {
                ['cpAccApprovalDateFrom', 'cpAccApprovalDateTo'].forEach(function(fid) {
                    var el = document.getElementById(fid);
                    if (el) el.removeAttribute('readonly');
                });
                cpAccApprovalFpDone = true;
                return;
            }
            cpAccApprovalFpDone = true;
            try {
                if (flatpickr.localize) flatpickr.localize(fpLocaleApprovals);
            } catch (err1) { /* ignore */ }
            ['cpAccApprovalDateFrom', 'cpAccApprovalDateTo'].forEach(function(fid) {
                var inp = document.getElementById(fid);
                if (!inp || inp._flatpickr) return;
                var initial = (inp.value || '').trim();
                flatpickr(inp, {
                    locale: fpLocaleApprovals,
                    dateFormat: 'Y-m-d',
                    allowInput: false,
                    disableMobile: true,
                    clickOpens: true,
                    defaultDate: initial || undefined,
                    onReady: function(selectedDates, _s, inst) {
                        if (selectedDates && selectedDates.length) window.cpAccSetFpInputYmd(inst, selectedDates);
                    },
                    onChange: function(selectedDates, _s, inst) {
                        window.cpAccSetFpInputYmd(inst, selectedDates || []);
                        cpAccApprovalApplyFilters();
                    }
                });
            });
            approvalModal.querySelectorAll('.cp-acc-clear-approval-date').forEach(function(btn) {
                if (btn.getAttribute('data-bound') === '1') return;
                btn.setAttribute('data-bound', '1');
                btn.addEventListener('click', function() {
                    var tid = btn.getAttribute('data-target');
                    var inp = document.getElementById(tid);
                    if (inp && inp._flatpickr) inp._flatpickr.clear();
                    else if (inp) inp.value = '';
                    cpAccApprovalApplyFilters();
                });
            });
        }
        window.addEventListener('load', initCpAccApprovalFlatpickr);

        cpAccApprovalApplyFilters();
    })();

    (function initGeneralLedger(){
        var ledgerModal = document.getElementById('ledgerModal');
        var viewModal = document.getElementById('approvalJournalViewModal');
        var viewBody = document.getElementById('approvalJournalViewBody');
        if (!ledgerModal) return;
        function cpAccLedgerGetSelectedIds() {
            var ids = [];
            ledgerModal.querySelectorAll('.cp-acc-ledger-cb:checked').forEach(function(cb){
                var v = parseInt(cb.value, 10);
                if (v) ids.push(v);
            });
            return ids;
        }
        function cpAccLedgerUpdateSelectionInfo() {
            var el = document.getElementById('cpAccLedgerSelectionInfo');
            if (el) el.textContent = cpAccLedgerGetSelectedIds().length + ' selected';
        }
        function csvEscapeCell(s) {
            s = String(s || '');
            if (/[",\n\r]/.test(s)) return '"' + s.replace(/"/g, '""') + '"';
            return s;
        }
        function cpAccLedgerExportRows(trList) {
            var headers = ['Entry Number', 'Journal Date', 'Total Debit', 'Total Credit', 'Debit Account', 'Credit Account', 'Description'];
            var lines = [headers.map(csvEscapeCell).join(',')];
            trList.forEach(function(tr){
                var tds = tr.querySelectorAll('td');
                if (tds.length < 9) return;
                var row = [];
                for (var i = 0; i < 7; i++) row.push(csvEscapeCell(tds[i].textContent.trim()));
                lines.push(row.join(','));
            });
            var blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8' });
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'general-ledger-export.csv';
            a.rel = 'noopener';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(a.href);
        }
        function cpAccLedgerApplyFilters() {
            var df = (document.getElementById('cpAccLedgerDateFrom') || {}).value || '';
            var dt = (document.getElementById('cpAccLedgerDateTo') || {}).value || '';
            var lim = parseInt((document.getElementById('cpAccLedgerPageSize') || {}).value, 10);
            if (!lim || lim < 1) lim = 10;
            var q = ((document.getElementById('cpAccLedgerSearch') || {}).value || '').trim().toLowerCase();
            var acct = (document.getElementById('cpAccLedgerAccountFilter') || {}).value || '';
            var shown = 0;
            ledgerModal.querySelectorAll('tr.cp-acc-ledger-row').forEach(function(tr){
                var ed = tr.getAttribute('data-entry-date') || '';
                var ok = true;
                if (ok && df && ed && ed < df) ok = false;
                if (ok && dt && ed && ed > dt) ok = false;
                if (ok && acct) {
                    var tds = tr.querySelectorAll('td');
                    var dr = (tds[4] && tds[4].textContent) ? tds[4].textContent.trim() : '';
                    var cr = (tds[5] && tds[5].textContent) ? tds[5].textContent.trim() : '';
                    if (dr.indexOf(acct) === -1 && cr.indexOf(acct) === -1) ok = false;
                }
                if (ok && q) {
                    var text = '';
                    var cells = tr.querySelectorAll('td');
                    for (var ci = 0; ci < 7 && ci < cells.length; ci++) text += ' ' + (cells[ci].textContent || '');
                    if (text.toLowerCase().indexOf(q) === -1) ok = false;
                }
                if (!ok) {
                    tr.style.display = 'none';
                    return;
                }
                shown++;
                tr.style.display = (shown <= lim) ? '' : 'none';
            });
            var selAll = document.getElementById('cpAccLedgerSelectAll');
            if (selAll) selAll.checked = false;
            ledgerModal.querySelectorAll('.cp-acc-ledger-cb').forEach(function(cb){ cb.checked = false; });
            cpAccLedgerUpdateSelectionInfo();
        }
        function cpAccLedgerDeleteIds(ids) {
            if (!ids.length) return Promise.resolve(null);
            return fetch(cpAccAccountingApiUrl('/accounting.php'), {
                method: 'POST',
                headers: cpAccWithCsrfHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({ bulk_delete_module: 'journal_entries', ids: ids }),
                credentials: 'same-origin',
                cache: 'no-store'
            }).then(function(r){ return cpAccAccountingResponseJson(r); });
        }
        function cpAccLedgerAfterDelete(res) {
            if (res && res.success) {
                window.cpAccReloadAndReopenModal('cpAccOpenLedgerModal');
            } else {
                alert((res && res.message) ? res.message : 'Delete failed.');
            }
        }
        function cpAccLedgerOpenJournalView(jid) {
            if (!viewBody || !viewModal || !jid) return;
            viewBody.innerHTML = '<p class="text-muted mb-0">Loading…</p>';
            viewModal.classList.add('is-open');
            document.body.style.overflow = 'hidden';
            fetch(cpAccAccountingApiUrl('/accounting.php?action=journal_entry_detail&journal_entry_id=' + jid), { credentials: 'same-origin', cache: 'no-store' })
                .then(function(r){ return cpAccAccountingResponseJson(r); })
                .then(function(res){
                    if (!res || !res.success || !res.entry) {
                        viewBody.innerHTML = '<p class="text-danger">' + ((res && res.message) ? res.message : 'Failed to load') + '</p>';
                        return;
                    }
                    var je = res.entry;
                    var lines = res.lines || [];
                    var html = '<div class="mb-3"><div class="small text-muted">Reference</div><div class="fw-bold">' + cpAccEscapeHtml(cpAccFormatGlReference(je.reference || '—')) + '</div>';
                    html += '<div class="small text-muted mt-2">Date</div><div>' + cpAccEscapeHtml(je.entry_date || '—') + '</div>';
                    html += '<div class="small text-muted mt-2">Status</div><div>' + cpAccEscapeHtml(je.status || '—') + '</div>';
                    html += '<div class="small text-muted mt-2">Description</div><div>' + cpAccEscapeHtml(je.description || '—') + '</div>';
                    html += '<div class="row mt-2"><div class="col-6 small text-muted">Total debit</div><div class="col-6 small text-muted">Total credit</div>';
                    html += '<div class="col-6">' + (parseFloat(je.total_debit) || 0).toFixed(2) + '</div><div class="col-6">' + (parseFloat(je.total_credit) || 0).toFixed(2) + '</div></div></div>';
                    html += '<h6 class="mb-2">Lines</h6><div class="cp-acc-table-wrap"><table class="cp-acc-table"><thead><tr><th>Description</th><th>Debit</th><th>Credit</th></tr></thead><tbody>';
                    if (!lines.length) {
                        html += '<tr><td colspan="3" class="text-muted">No lines</td></tr>';
                    } else {
                        lines.forEach(function(L){
                            html += '<tr><td>' + cpAccEscapeHtml(L.description || '—') + '</td><td>' + (parseFloat(L.debit) || 0).toFixed(2) + '</td><td>' + (parseFloat(L.credit) || 0).toFixed(2) + '</td></tr>';
                        });
                    }
                    html += '</tbody></table></div>';
                    viewBody.innerHTML = html;
                })
                .catch(function(){ viewBody.innerHTML = '<p class="text-danger">Request failed.</p>'; });
        }
        var refBtn = document.getElementById('cpAccLedgerRefreshBtn');
        if (refBtn) refBtn.addEventListener('click', function(){
            try { sessionStorage.setItem('cpAccOpenLedgerModal', '1'); } catch (e) {}
            location.reload();
        });
        var normalizeBtn = document.getElementById('cpAccNormalizeNumbersBtn');
        if (normalizeBtn) normalizeBtn.addEventListener('click', function(){
            if (!confirm('Normalize all journal/receipt numbers to GL-00001 and RC-00001 formats?')) return;
            normalizeBtn.disabled = true;
            fetch(cpAccAccountingApiUrl('/accounting.php?action=normalize_numbers'), {
                method: 'POST',
                headers: cpAccWithCsrfHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({}),
                credentials: 'same-origin',
                cache: 'no-store'
            })
                .then(function(r){ return cpAccAccountingResponseJson(r); })
                .then(function(res){
                    if (res && res.success) {
                        var st = (res.result || {});
                        var msg = 'Done.\nJournals changed: ' + (st.journals_changed || 0) + '\nReceipts changed: ' + (st.receipts_changed || 0);
                        if (window.cpAccShowToast) window.cpAccShowToast(msg);
                        else alert(msg);
                        try { sessionStorage.setItem('cpAccOpenLedgerModal', '1'); } catch (e) {}
                        location.reload();
                    } else {
                        alert((res && res.message) ? res.message : 'Normalization failed.');
                    }
                })
                .catch(function(){ alert('Request failed.'); })
                .finally(function(){ normalizeBtn.disabled = false; });
        });
        var prBtn = document.getElementById('cpAccLedgerPrintBtn');
        if (prBtn) prBtn.addEventListener('click', function(){ window.print(); });
        var expVis = document.getElementById('cpAccLedgerExportVisibleBtn');
        function cpAccLedgerGetVisibleRows() {
            var rows = [];
            ledgerModal.querySelectorAll('tr.cp-acc-ledger-row').forEach(function(tr){
                if (tr.style.display === 'none') return;
                rows.push(tr);
            });
            return rows;
        }
        if (expVis) expVis.addEventListener('click', function(){
            var rows = cpAccLedgerGetVisibleRows();
            if (!rows.length) { alert('No rows to export.'); return; }
            cpAccLedgerExportRows(rows);
        });
        var copyVis = document.getElementById('cpAccLedgerCopyVisibleBtn');
        if (copyVis) copyVis.addEventListener('click', function(){
            var rows = cpAccLedgerGetVisibleRows();
            if (!rows.length) { alert('No rows to copy.'); return; }
            var headers = ['Entry Number', 'Journal Date', 'Total Debit', 'Total Credit', 'Debit Account', 'Credit Account', 'Description'];
            var lines = [headers.join('\t')];
            rows.forEach(function(tr){
                var tds = tr.querySelectorAll('td');
                if (tds.length < 9) return;
                var row = [];
                for (var i = 0; i < 7; i++) row.push((tds[i].textContent || '').trim().replace(/\t/g, ' '));
                lines.push(row.join('\t'));
            });
            var text = lines.join('\n');
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function(){ alert('Copied visible rows to clipboard.'); }).catch(function(){ alert('Could not copy. Select and copy manually.'); });
            } else {
                try {
                    var ta = document.createElement('textarea');
                    ta.value = text;
                    document.body.appendChild(ta);
                    ta.select();
                    document.execCommand('copy');
                    document.body.removeChild(ta);
                    alert('Copied visible rows to clipboard.');
                } catch (e2) {
                    alert('Clipboard not available in this browser.');
                }
            }
        });
        var excelVis = document.getElementById('cpAccLedgerExcelVisibleBtn');
        if (excelVis) excelVis.addEventListener('click', function(){
            var rows = cpAccLedgerGetVisibleRows();
            if (!rows.length) { alert('No rows to export.'); return; }
            var headers = ['Entry Number', 'Journal Date', 'Total Debit', 'Total Credit', 'Debit Account', 'Credit Account', 'Description'];
            var lines = [headers.map(csvEscapeCell).join(',')];
            rows.forEach(function(tr){
                var tds = tr.querySelectorAll('td');
                if (tds.length < 9) return;
                var row = [];
                for (var i = 0; i < 7; i++) row.push(csvEscapeCell(tds[i].textContent.trim()));
                lines.push(row.join(','));
            });
            var blob = new Blob([lines.join('\n')], { type: 'application/vnd.ms-excel;charset=utf-8' });
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'general-ledger.xls';
            a.rel = 'noopener';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(a.href);
        });
        var bulkDel = document.getElementById('cpAccLedgerBulkDelete');
        if (bulkDel) bulkDel.addEventListener('click', function(){
            var ids = cpAccLedgerGetSelectedIds();
            if (!ids.length) { alert('Select one or more entries (checkboxes).'); return; }
            if (!confirm('Delete ' + ids.length + ' journal entr' + (ids.length === 1 ? 'y' : 'ies') + '? This cannot be undone.')) return;
            cpAccLedgerDeleteIds(ids).then(cpAccLedgerAfterDelete);
        });
        var bulkExp = document.getElementById('cpAccLedgerBulkExport');
        if (bulkExp) bulkExp.addEventListener('click', function(){
            var rows = [];
            ledgerModal.querySelectorAll('.cp-acc-ledger-cb:checked').forEach(function(cb){
                var tr = cb.closest('tr');
                if (tr) rows.push(tr);
            });
            if (!rows.length) { alert('Select one or more rows to export.'); return; }
            cpAccLedgerExportRows(rows);
        });
        ledgerModal.addEventListener('change', function(e){
            if (e.target && e.target.id === 'cpAccLedgerSelectAll') {
                var on = e.target.checked;
                ledgerModal.querySelectorAll('tr.cp-acc-ledger-row').forEach(function(tr){
                    if (tr.style.display === 'none') return;
                    var cb = tr.querySelector('.cp-acc-ledger-cb');
                    if (cb) cb.checked = on;
                });
                cpAccLedgerUpdateSelectionInfo();
                return;
            }
            if (e.target && e.target.classList && e.target.classList.contains('cp-acc-ledger-cb')) {
                cpAccLedgerUpdateSelectionInfo();
            }
        });
        ['cpAccLedgerDateFrom', 'cpAccLedgerDateTo', 'cpAccLedgerPageSize', 'cpAccLedgerAccountFilter'].forEach(function(id){
            var el = document.getElementById(id);
            if (el) el.addEventListener('change', cpAccLedgerApplyFilters);
        });
        var searchIn = document.getElementById('cpAccLedgerSearch');
        var searchTimer;
        if (searchIn) searchIn.addEventListener('input', function(){
            clearTimeout(searchTimer);
            searchTimer = setTimeout(cpAccLedgerApplyFilters, 200);
        });
        ledgerModal.addEventListener('click', function(e){
            var viewBtn = e.target.closest('.cp-acc-ledger-view');
            if (viewBtn) {
                e.preventDefault();
                var jid = parseInt(viewBtn.getAttribute('data-journal-id'), 10);
                if (!jid) return;
                cpAccLedgerOpenJournalView(jid);
                return;
            }
            var editBtn = e.target.closest('.cp-acc-ledger-edit');
            if (editBtn) {
                e.preventDefault();
                var ejid = parseInt(editBtn.getAttribute('data-journal-id'), 10);
                if (ejid) openLedgerJournalEdit(ejid);
                return;
            }
            var delBtn = e.target.closest('.cp-acc-ledger-delete');
            if (delBtn) {
                e.preventDefault();
                var did = parseInt(delBtn.getAttribute('data-journal-id'), 10);
                if (!did) return;
                if (!confirm('Delete this journal entry? This cannot be undone.')) return;
                cpAccLedgerDeleteIds([did]).then(cpAccLedgerAfterDelete);
            }
        });
        var fpLocaleLedger = {
            weekdays: { shorthand: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'], longhand: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] },
            months: { shorthand: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'], longhand: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'] },
            firstDayOfWeek: 0,
            rangeSeparator: ' to ',
            weekAbbreviation: 'Wk'
        };
        function bindLedgerDateClearButtonsOnce() {
            ledgerModal.querySelectorAll('.cp-acc-ledger-date-clear').forEach(function(btn) {
                if (btn.getAttribute('data-bound') === '1') return;
                btn.setAttribute('data-bound', '1');
                btn.addEventListener('click', function() {
                    var tid = btn.getAttribute('data-target');
                    var inp = document.getElementById(tid);
                    if (inp && inp._flatpickr) inp._flatpickr.clear();
                    else if (inp) inp.value = '';
                    cpAccLedgerApplyFilters();
                });
            });
        }
        function initLedgerFilterFlatpickr() {
            if (typeof flatpickr === 'undefined') return false;
            try {
                if (flatpickr.localize) flatpickr.localize(fpLocaleLedger);
            } catch (err1) { /* ignore */ }
            bindLedgerDateClearButtonsOnce();
            var created = false;
            ['cpAccLedgerDateFrom', 'cpAccLedgerDateTo'].forEach(function(fid) {
                var inp = document.getElementById(fid);
                if (!inp || inp._flatpickr) return;
                var initial = (inp.value || '').trim();
                flatpickr(inp, {
                    locale: fpLocaleLedger,
                    dateFormat: 'Y-m-d',
                    allowInput: false,
                    disableMobile: true,
                    clickOpens: true,
                    defaultDate: initial || undefined,
                    onReady: function(selectedDates, _s, inst) {
                        if (selectedDates && selectedDates.length) window.cpAccSetFpInputYmd(inst, selectedDates);
                    },
                    onChange: function(selectedDates, _s, inst) {
                        window.cpAccSetFpInputYmd(inst, selectedDates || []);
                        cpAccLedgerApplyFilters();
                    }
                });
                created = true;
            });
            return created;
        }
        function scheduleLedgerFilterFlatpickr() {
            var tries = 0;
            function tick() {
                if (initLedgerFilterFlatpickr()) return;
                tries++;
                if (tries < 40) setTimeout(tick, 100);
                else {
                    ['cpAccLedgerDateFrom', 'cpAccLedgerDateTo'].forEach(function(fid) {
                        var el = document.getElementById(fid);
                        if (el) el.removeAttribute('readonly');
                    });
                }
            }
            tick();
        }
        scheduleLedgerFilterFlatpickr();
        window.addEventListener('load', scheduleLedgerFilterFlatpickr);
        if (typeof MutationObserver !== 'undefined') {
            var ledgerMo = new MutationObserver(function() {
                if (ledgerModal.classList.contains('is-open')) scheduleLedgerFilterFlatpickr();
            });
            ledgerMo.observe(ledgerModal, { attributes: true, attributeFilter: ['class'] });
        }
        document.querySelectorAll('[data-cp-acc-modal="ledgerModal"]').forEach(function(link) {
            link.addEventListener('click', function() { setTimeout(scheduleLedgerFilterFlatpickr, 30); });
        });

        var jeEditModal = document.getElementById('ledgerJournalEditModal');
        var jeDebitBody = document.getElementById('cpAccJeEditDebitBody');
        var jeCreditBody = document.getElementById('cpAccJeEditCreditBody');
        function cpAccJeRefreshBodyOverflow() {
            var open = document.querySelector('.cp-acc-modal.is-open');
            document.body.style.overflow = open ? 'hidden' : '';
        }
        function cpAccJeDestroyEditFp() {
            var inp = document.getElementById('cpAccJeEditDate');
            if (inp && inp._flatpickr) inp._flatpickr.destroy();
        }
        function cpAccJeInitEditFp(dateStr) {
            cpAccJeDestroyEditFp();
            var inp = document.getElementById('cpAccJeEditDate');
            if (!inp) return;
            inp.value = (dateStr || '').trim();
            if (typeof flatpickr === 'undefined') {
                inp.removeAttribute('readonly');
                return;
            }
            try {
                if (flatpickr.localize) flatpickr.localize(fpLocaleLedger);
            } catch (e2) { /* ignore */ }
            flatpickr(inp, {
                locale: fpLocaleLedger,
                dateFormat: 'Y-m-d',
                allowInput: false,
                disableMobile: true,
                clickOpens: true,
                defaultDate: (dateStr || '').trim() || undefined,
                onReady: function(selectedDates, _s, inst) {
                    if (selectedDates && selectedDates.length) window.cpAccSetFpInputYmd(inst, selectedDates);
                },
                onChange: function(selectedDates, _s, inst) {
                    window.cpAccSetFpInputYmd(inst, selectedDates || []);
                }
            });
        }
        function cpAccJeBuildAccountSelect() {
            var sel = document.createElement('select');
            sel.className = 'form-select form-select-sm cp-acc-je-line-account';
            var o0 = document.createElement('option');
            o0.value = '';
            o0.textContent = 'Select Account';
            sel.appendChild(o0);
            (window.cpAccChartAccountsForJournal || []).forEach(function(a) {
                if (!a || !a.id) return;
                var o = document.createElement('option');
                o.value = String(a.id);
                o.textContent = (a.code ? a.code + ' — ' : '') + (a.name || '');
                sel.appendChild(o);
            });
            return sel;
        }
        function cpAccJeBuildCostCenterSelect() {
            var sel = document.createElement('select');
            sel.className = 'form-select form-select-sm cp-acc-je-line-costcenter';
            var o0 = document.createElement('option');
            o0.value = '';
            o0.textContent = '- Main Center';
            sel.appendChild(o0);
            (window.cpAccCostCentersForJournal || []).forEach(function(c) {
                if (!c || !c.id) return;
                var o = document.createElement('option');
                o.value = String(c.id);
                o.textContent = (c.code ? c.code + ' — ' : '') + (c.name || '');
                sel.appendChild(o);
            });
            return sel;
        }
        function cpAccJeUpdateTotals() {
            function sumBody(body) {
                var t = 0;
                if (!body) return 0;
                body.querySelectorAll('.cp-acc-je-line-amount').forEach(function(inp) {
                    t += window.cpAccParseDecimal(inp.value);
                });
                return t;
            }
            var tdD = sumBody(jeDebitBody);
            var tdC = sumBody(jeCreditBody);
            var elDr = document.getElementById('cpAccJeEditTotalDebit');
            var elCr = document.getElementById('cpAccJeEditTotalCredit');
            var elMsg = document.getElementById('cpAccJeEditBalanceMsg');
            if (elDr) elDr.textContent = tdD.toFixed(2);
            if (elCr) elCr.textContent = tdC.toFixed(2);
            if (elMsg) {
                var diff = Math.abs(tdD - tdC);
                elMsg.textContent = diff < 0.001 ? 'BALANCED' : ('UNBALANCED: ' + diff.toFixed(2));
            }
        }
        function cpAccJeAppendJournalRow(tbody, line, side) {
            line = line || {};
            if (!tbody) return;
            var tr = document.createElement('tr');
            tr.className = 'cp-acc-je-' + side + '-row';
            var tdAcc = document.createElement('td');
            var sel = cpAccJeBuildAccountSelect();
            var nameInp = document.createElement('input');
            nameInp.type = 'text';
            nameInp.className = 'form-control form-control-sm mt-1 cp-acc-je-line-acct-name d-none';
            nameInp.placeholder = 'Account name (if not in chart)';
            tdAcc.appendChild(sel);
            tdAcc.appendChild(nameInp);
            var tdCc = document.createElement('td');
            var ccSel = cpAccJeBuildCostCenterSelect();
            tdCc.appendChild(ccSel);
            var tdDesc = document.createElement('td');
            var inDesc = document.createElement('input');
            inDesc.type = 'text';
            inDesc.className = 'form-control form-control-sm cp-acc-je-line-desc';
            inDesc.placeholder = 'Description';
            tdDesc.appendChild(inDesc);
            var tdVat = document.createElement('td');
            var vatCb = document.createElement('input');
            vatCb.type = 'checkbox';
            vatCb.className = 'form-check-input cp-acc-je-line-vat';
            vatCb.title = 'VAT (UI only; not stored on line yet)';
            tdVat.appendChild(vatCb);
            var tdAmt = document.createElement('td');
            var inAmt = document.createElement('input');
            inAmt.type = 'text';
            inAmt.setAttribute('inputmode', 'decimal');
            inAmt.setAttribute('lang', 'en');
            inAmt.setAttribute('dir', 'ltr');
            inAmt.setAttribute('autocomplete', 'off');
            inAmt.className = 'form-control form-control-sm cp-acc-je-line-amount cp-acc-amount-en';
            inAmt.placeholder = '0.00';
            tdAmt.appendChild(inAmt);
            var tdRm = document.createElement('td');
            var btnRm = document.createElement('button');
            btnRm.type = 'button';
            btnRm.className = 'btn btn-sm btn-outline-danger cp-acc-je-line-remove';
            btnRm.setAttribute('aria-label', 'Remove line');
            btnRm.innerHTML = '&times;';
            tdRm.appendChild(btnRm);
            tr.appendChild(tdAcc);
            tr.appendChild(tdCc);
            tr.appendChild(tdDesc);
            tr.appendChild(tdVat);
            tr.appendChild(tdAmt);
            tr.appendChild(tdRm);
            tbody.appendChild(tr);
            var aid = parseInt(line.account_id, 10) || 0;
            if (aid) {
                sel.value = String(aid);
            } else if ((line.account_name || '').trim() !== '') {
                sel.value = '';
                nameInp.classList.remove('d-none');
                nameInp.value = (line.account_name || '').trim();
            }
            inDesc.value = (line.description || '').trim();
            var amt = side === 'debit' ? (parseFloat(line.debit) || 0) : (parseFloat(line.credit) || 0);
            if (amt > 0) inAmt.value = amt.toFixed(2);
            function syncNameVisibility() {
                if (!sel.value) nameInp.classList.remove('d-none');
                else { nameInp.classList.add('d-none'); nameInp.value = ''; }
            }
            sel.addEventListener('change', function() { syncNameVisibility(); });
            syncNameVisibility();
            btnRm.addEventListener('click', function() {
                if (tbody.querySelectorAll('tr').length <= 1) {
                    alert('At least one line is required in each section.');
                    return;
                }
                tr.remove();
                cpAccJeUpdateTotals();
            });
            inAmt.addEventListener('input', cpAccJeUpdateTotals);
            inAmt.addEventListener('change', cpAccJeUpdateTotals);
        }
        function openLedgerJournalEdit(jid) {
            if (!jeEditModal || !jeDebitBody || !jeCreditBody) return;
            document.getElementById('cpAccJeEditId').value = String(jid);
            jeDebitBody.innerHTML = '';
            jeCreditBody.innerHTML = '';
            document.getElementById('cpAccJeEditReference').value = '';
            document.getElementById('cpAccJeEditDescription').value = '';
            var cust = document.getElementById('cpAccJeEditCustomer');
            if (cust) cust.value = '';
            document.getElementById('cpAccJeEditStatusHint').textContent = 'Loading…';
            jeEditModal.classList.add('is-open');
            document.body.style.overflow = 'hidden';
            fetch(cpAccAccountingApiUrl('/accounting.php?action=journal_entry_detail&journal_entry_id=' + jid), { credentials: 'same-origin', cache: 'no-store' })
                .then(function(r){ return cpAccAccountingResponseJson(r); })
                .then(function(res){
                    if (!res || !res.success || !res.entry) {
                        alert((res && res.message) ? res.message : 'Could not load journal.');
                        jeEditModal.classList.remove('is-open');
                        cpAccJeRefreshBodyOverflow();
                        return;
                    }
                    var je = res.entry;
                    var lines = res.lines || [];
                    document.getElementById('cpAccJeEditReference').value = je.reference || '';
                    document.getElementById('cpAccJeEditDescription').value = je.description || '';
                    var st = (je.status || '').toLowerCase();
                    document.getElementById('cpAccJeEditStatusHint').textContent = st ? ('Current status: ' + st + '. Saving updates this entry and its lines.') : '';
                    var debLines = lines.filter(function(L) { return (parseFloat(L.debit) || 0) > 0; });
                    var creLines = lines.filter(function(L) { return (parseFloat(L.credit) || 0) > 0; });
                    if (debLines.length === 0 && creLines.length === 0) {
                        cpAccJeAppendJournalRow(jeDebitBody, {}, 'debit');
                        cpAccJeAppendJournalRow(jeCreditBody, {}, 'credit');
                    } else {
                        if (debLines.length === 0) debLines = [{}];
                        if (creLines.length === 0) creLines = [{}];
                        debLines.forEach(function(L) { cpAccJeAppendJournalRow(jeDebitBody, L, 'debit'); });
                        creLines.forEach(function(L) { cpAccJeAppendJournalRow(jeCreditBody, L, 'credit'); });
                    }
                    cpAccJeUpdateTotals();
                    setTimeout(function() {
                        cpAccJeInitEditFp(je.entry_date || '');
                        if (typeof flatpickr === 'undefined') scheduleLedgerFilterFlatpickr();
                    }, 0);
                })
                .catch(function() {
                    alert('Request failed.');
                    jeEditModal.classList.remove('is-open');
                    cpAccJeRefreshBodyOverflow();
                });
        }
        function closeLedgerJournalEdit() {
            if (!jeEditModal) return;
            cpAccJeDestroyEditFp();
            jeEditModal.classList.remove('is-open');
            cpAccJeRefreshBodyOverflow();
        }
        if (jeEditModal) {
            jeEditModal.querySelectorAll('[data-cp-acc-je-edit-close]').forEach(function(b) {
                b.addEventListener('click', function(e) { e.preventDefault(); closeLedgerJournalEdit(); });
            });
            jeEditModal.addEventListener('click', function(e) { if (e.target === jeEditModal) closeLedgerJournalEdit(); });
            jeEditModal.addEventListener('input', function(e) {
                if (!e.target.classList || !e.target.classList.contains('cp-acc-je-line-amount')) return;
                window.cpAccSanitizeAmountTyping(e.target);
                cpAccJeUpdateTotals();
            });
            jeEditModal.addEventListener('blur', function(e) {
                if (!e.target.classList || !e.target.classList.contains('cp-acc-je-line-amount')) return;
                window.cpAccFormatAmountBlur(e.target);
                cpAccJeUpdateTotals();
            }, true);
            var addDebit = document.getElementById('cpAccJeEditAddDebit');
            if (addDebit) addDebit.addEventListener('click', function() { cpAccJeAppendJournalRow(jeDebitBody, {}, 'debit'); cpAccJeUpdateTotals(); });
            var addCredit = document.getElementById('cpAccJeEditAddCredit');
            if (addCredit) addCredit.addEventListener('click', function() { cpAccJeAppendJournalRow(jeCreditBody, {}, 'credit'); cpAccJeUpdateTotals(); });
            var saveBtn = document.getElementById('cpAccJeEditSave');
            if (saveBtn) saveBtn.addEventListener('click', function() {
                var idEl = document.getElementById('cpAccJeEditId');
                var jid = parseInt(idEl && idEl.value, 10);
                if (!jid) return;
                var ref = (document.getElementById('cpAccJeEditReference') || {}).value || '';
                var desc = (document.getElementById('cpAccJeEditDescription') || {}).value || '';
                var dateInp = document.getElementById('cpAccJeEditDate');
                var entryDate = '';
                if (dateInp && dateInp._flatpickr && dateInp._flatpickr.selectedDates.length) {
                    var d0 = dateInp._flatpickr.selectedDates[0];
                    entryDate = d0.getFullYear() + '-' + String(d0.getMonth() + 1).padStart(2, '0') + '-' + String(d0.getDate()).padStart(2, '0');
                } else {
                    entryDate = (dateInp && dateInp.value) ? dateInp.value.trim() : '';
                }
                function collectLines(body, side) {
                    var out = [];
                    body.querySelectorAll('tr').forEach(function(tr) {
                        var sel = tr.querySelector('.cp-acc-je-line-account');
                        var aid = sel ? (parseInt(sel.value, 10) || 0) : 0;
                        var nameEl = tr.querySelector('.cp-acc-je-line-acct-name');
                        var aname = nameEl ? nameEl.value.trim() : '';
                        var amt = window.cpAccParseDecimal((tr.querySelector('.cp-acc-je-line-amount') || {}).value);
                        var ld = ((tr.querySelector('.cp-acc-je-line-desc') || {}).value || '').trim();
                        if (amt <= 0 && !aid && !aname) return;
                        if (side === 'debit') out.push({ account_id: aid, account_name: aname, debit: amt, credit: 0, description: ld });
                        else out.push({ account_id: aid, account_name: aname, debit: 0, credit: amt, description: ld });
                    });
                    return out;
                }
                var lines = collectLines(jeDebitBody, 'debit').concat(collectLines(jeCreditBody, 'credit'));
                saveBtn.disabled = true;
                fetch(cpAccAccountingApiUrl('/accounting.php?action=journal_entry_save'), {
                    method: 'POST',
                    headers: cpAccWithCsrfHeaders({ 'Content-Type': 'application/json' }),
                    body: JSON.stringify({ journal_entry_id: jid, reference: ref.trim(), entry_date: entryDate, description: desc, lines: lines }),
                    credentials: 'same-origin',
                    cache: 'no-store'
                }).then(function(r) { return cpAccAccountingResponseJson(r); }).then(function(res) {
                    if (res && res.success) {
                        closeLedgerJournalEdit();
                        window.cpAccReloadAndReopenModal('cpAccOpenLedgerModal');
                    } else {
                        alert((res && res.message) ? res.message : 'Save failed.');
                    }
                }).catch(function() { alert('Save request failed.'); }).finally(function() { saveBtn.disabled = false; });
            });
        }
        cpAccLedgerApplyFilters();
    })();

    (function initNewJournalEntryModal() {
        var modal = document.getElementById('newJournalModal');
        var debBody = document.getElementById('cpAccNewJeDebitBody');
        var creBody = document.getElementById('cpAccNewJeCreditBody');
        if (!modal || !debBody || !creBody) return;
        var fpLocNewJe = {
            weekdays: { shorthand: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'], longhand: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] },
            months: { shorthand: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'], longhand: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'] },
            firstDayOfWeek: 0,
            rangeSeparator: ' to ',
            weekAbbreviation: 'Wk'
        };
        function initNewJeFlatpickr() {
            var inp = document.getElementById('cpAccNewJeDate');
            if (!inp || inp._flatpickr || typeof flatpickr === 'undefined') return;
            try { if (flatpickr.localize) flatpickr.localize(fpLocNewJe); } catch (e0) { /* ignore */ }
            var v = (inp.value || '').trim();
            flatpickr(inp, {
                locale: fpLocNewJe,
                dateFormat: 'Y-m-d',
                allowInput: false,
                disableMobile: true,
                clickOpens: true,
                defaultDate: v || undefined,
                onReady: function(selectedDates, _s, inst) {
                    if (selectedDates && selectedDates.length) window.cpAccSetFpInputYmd(inst, selectedDates);
                },
                onChange: function(selectedDates, _s, inst) {
                    window.cpAccSetFpInputYmd(inst, selectedDates || []);
                }
            });
        }
        function scheduleNewJeFlatpickr() {
            var n = 0;
            function t() {
                initNewJeFlatpickr();
                var inp = document.getElementById('cpAccNewJeDate');
                if (inp && !inp._flatpickr && n < 35) {
                    n++;
                    setTimeout(t, 100);
                }
            }
            t();
        }
        document.querySelectorAll('[data-cp-acc-modal="newJournalModal"]').forEach(function(link) {
            link.addEventListener('click', function() { setTimeout(scheduleNewJeFlatpickr, 0); });
        });
        if (typeof MutationObserver !== 'undefined') {
            var moNj = new MutationObserver(function() {
                if (modal.classList.contains('is-open')) scheduleNewJeFlatpickr();
            });
            moNj.observe(modal, { attributes: true, attributeFilter: ['class'] });
        }
        scheduleNewJeFlatpickr();

        function sumBody(body) {
            var t = 0;
            body.querySelectorAll('.cp-acc-new-je-amount').forEach(function(inp) { t += window.cpAccParseDecimal(inp.value); });
            return t;
        }
        function updateNewJeTotals() {
            var td = sumBody(debBody);
            var tc = sumBody(creBody);
            var elD = document.getElementById('cpAccNewJeTotalDebit');
            var elC = document.getElementById('cpAccNewJeTotalCredit');
            var elM = document.getElementById('cpAccNewJeBalanceMsg');
            if (elD) elD.textContent = td.toFixed(2);
            if (elC) elC.textContent = tc.toFixed(2);
            if (elM) {
                var diff = Math.abs(td - tc);
                elM.textContent = diff < 0.001 ? 'BALANCED' : ('UNBALANCED: ' + diff.toFixed(2));
            }
        }
        function bindAccountNameToggle(tr) {
            var sel = tr.querySelector('.cp-acc-new-je-account');
            var nm = tr.querySelector('.cp-acc-new-je-acct-name');
            if (!sel || !nm) return;
            function sync() {
                if (!sel.value) nm.classList.remove('d-none');
                else { nm.classList.add('d-none'); nm.value = ''; }
            }
            sel.addEventListener('change', sync);
            sync();
        }
        function resetRow(tr) {
            tr.querySelectorAll('select').forEach(function(s) { s.selectedIndex = 0; });
            tr.querySelectorAll('input[type=text]').forEach(function(i) {
                if (i.classList.contains('cp-acc-new-je-amount')) i.value = '0.00';
                else i.value = '';
            });
            tr.querySelectorAll('input[type=checkbox]').forEach(function(c) { c.checked = false; });
            tr.querySelectorAll('.cp-acc-new-je-acct-name').forEach(function(n) { n.classList.add('d-none'); });
        }
        function bindRow(tr) {
            bindAccountNameToggle(tr);
        }
        modal.addEventListener('input', function(e) {
            if (!e.target.classList || !e.target.classList.contains('cp-acc-new-je-amount')) return;
            window.cpAccSanitizeAmountTyping(e.target);
            updateNewJeTotals();
        });
        modal.addEventListener('blur', function(e) {
            if (!e.target.classList || !e.target.classList.contains('cp-acc-new-je-amount')) return;
            window.cpAccFormatAmountBlur(e.target);
            updateNewJeTotals();
        }, true);
        debBody.querySelectorAll('tr').forEach(bindRow);
        creBody.querySelectorAll('tr').forEach(bindRow);
        updateNewJeTotals();

        modal.addEventListener('click', function(e) {
            var b = e.target.closest('[data-cp-acc-new-je-add]');
            if (!b) return;
            var side = b.getAttribute('data-cp-acc-new-je-add');
            var body = side === 'debit' ? debBody : creBody;
            var last = body.querySelector('tr:last-child');
            if (!last) return;
            var nu = last.cloneNode(true);
            resetRow(nu);
            body.appendChild(nu);
            bindRow(nu);
            updateNewJeTotals();
        });

        var saveBtn = document.getElementById('cpAccNewJeSaveBtn');
        if (saveBtn) saveBtn.addEventListener('click', function() {
            var cidEl = document.getElementById('cpAccNewJeCountryId');
            var countryId = cidEl ? parseInt(cidEl.value, 10) || 0 : 0;
            var desc = ((document.getElementById('cpAccNewJeDescription') || {}).value || '').trim();
            var dateInp = document.getElementById('cpAccNewJeDate');
            var entryDate = '';
            if (dateInp && dateInp._flatpickr && dateInp._flatpickr.selectedDates.length) {
                var d0 = dateInp._flatpickr.selectedDates[0];
                entryDate = d0.getFullYear() + '-' + String(d0.getMonth() + 1).padStart(2, '0') + '-' + String(d0.getDate()).padStart(2, '0');
            } else {
                entryDate = (dateInp && dateInp.value) ? dateInp.value.trim() : '';
            }
            function collectLines(body, side) {
                var out = [];
                body.querySelectorAll('tr').forEach(function(tr) {
                    var sel = tr.querySelector('.cp-acc-new-je-account');
                    var aid = sel ? (parseInt(sel.value, 10) || 0) : 0;
                    var nameEl = tr.querySelector('.cp-acc-new-je-acct-name');
                    var aname = nameEl ? nameEl.value.trim() : '';
                    var amt = window.cpAccParseDecimal((tr.querySelector('.cp-acc-new-je-amount') || {}).value);
                    var ld = ((tr.querySelector('.cp-acc-new-je-line-desc') || {}).value || '').trim();
                    if (amt <= 0 && !aid && !aname) return;
                    if (side === 'debit') out.push({ account_id: aid, account_name: aname, debit: amt, credit: 0, description: ld });
                    else out.push({ account_id: aid, account_name: aname, debit: 0, credit: amt, description: ld });
                });
                return out;
            }
            var lines = collectLines(debBody, 'debit').concat(collectLines(creBody, 'credit'));
            if (!entryDate) { alert('Journal date is required.'); return; }
            if (!desc) { alert('Description is required.'); return; }
            saveBtn.disabled = true;
            fetch(cpAccAccountingApiUrl('/accounting.php?action=journal_entry_create'), {
                method: 'POST',
                headers: cpAccWithCsrfHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({ country_id: countryId, entry_date: entryDate, description: desc, lines: lines }),
                credentials: 'same-origin',
                cache: 'no-store'
            }).then(function(r) { return cpAccAccountingResponseJson(r); }).then(function(res) {
                if (res && res.success) {
                    if (res.reference && window.cpAccShowToast) {
                        window.cpAccShowToast('Journal saved — reference ' + res.reference);
                    }
                    modal.classList.remove('is-open');
                    var nj = document.getElementById('cpAccNewJeDate');
                    if (nj && nj._flatpickr) nj._flatpickr.destroy();
                    var anyOpen = document.querySelector('.cp-acc-modal.is-open');
                    document.body.style.overflow = anyOpen ? 'hidden' : '';
                    window.cpAccReloadAndReopenModal && window.cpAccReloadAndReopenModal('cpAccOpenLedgerModal');
                } else {
                    alert((res && res.message) ? res.message : 'Save failed.');
                }
            }).catch(function() { alert('Save request failed.'); }).finally(function() { saveBtn.disabled = false; });
        });
    })();

    (function initReceiptExpenseJeLikeModals() {
        function accJson(path, body) {
            var url = cpAccAccountingApiUrl(path);
            return fetch(url, {
                method: 'POST',
                headers: cpAccWithCsrfHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify(body),
                credentials: 'same-origin',
                cache: 'no-store'
            }).then(function(r) { return cpAccAccountingResponseJson(r); });
        }
        var fpLoc = window.cpAccFpLocaleEn || {
            weekdays: { shorthand: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'], longhand: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] },
            months: { shorthand: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'], longhand: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'] },
            firstDayOfWeek: 0,
            rangeSeparator: ' to ',
            weekAbbreviation: 'Wk'
        };
        function initJeLikeFp(inputId) {
            var inp = document.getElementById(inputId);
            if (!inp || inp._flatpickr || typeof flatpickr === 'undefined') return;
            try { if (flatpickr.localize) flatpickr.localize(fpLoc); } catch (e0) { /* ignore */ }
            var v = (inp.value || '').trim();
            flatpickr(inp, {
                locale: fpLoc,
                dateFormat: 'Y-m-d',
                allowInput: false,
                disableMobile: true,
                clickOpens: true,
                defaultDate: v || undefined,
                onReady: function(selectedDates, _s, inst) {
                    if (selectedDates && selectedDates.length) window.cpAccSetFpInputYmd(inst, selectedDates);
                },
                onChange: function(selectedDates, _s, inst) {
                    window.cpAccSetFpInputYmd(inst, selectedDates || []);
                }
            });
        }
        function scheduleJeLikeFp(inputId) {
            var n = 0;
            function t() {
                initJeLikeFp(inputId);
                var inp = document.getElementById(inputId);
                if (inp && !inp._flatpickr && n < 35) {
                    n++;
                    setTimeout(t, 100);
                }
            }
            t();
        }
        function bindJeLikeCore(modalEl, debBody, creBody, totalDebitId, totalCreditId, balanceMsgId) {
            function sumBody(body) {
                var t = 0;
                body.querySelectorAll('.cp-acc-new-je-amount').forEach(function(inp) { t += window.cpAccParseDecimal(inp.value); });
                return t;
            }
            function updateTotals() {
                var td = sumBody(debBody);
                var tc = sumBody(creBody);
                var elD = document.getElementById(totalDebitId);
                var elC = document.getElementById(totalCreditId);
                var elM = document.getElementById(balanceMsgId);
                if (elD) elD.textContent = td.toFixed(2);
                if (elC) elC.textContent = tc.toFixed(2);
                if (elM) {
                    var diff = Math.abs(td - tc);
                    elM.textContent = diff < 0.001 ? 'BALANCED' : ('UNBALANCED: ' + diff.toFixed(2));
                }
            }
            function bindAccountNameToggle(tr) {
                var sel = tr.querySelector('.cp-acc-new-je-account');
                var nm = tr.querySelector('.cp-acc-new-je-acct-name');
                if (!sel || !nm) return;
                function sync() {
                    if (!sel.value) nm.classList.remove('d-none');
                    else { nm.classList.add('d-none'); nm.value = ''; }
                }
                sel.addEventListener('change', sync);
                sync();
            }
            function resetRow(tr) {
                tr.querySelectorAll('select').forEach(function(s) { s.selectedIndex = 0; });
                tr.querySelectorAll('input[type=text]').forEach(function(i) {
                    if (i.classList.contains('cp-acc-new-je-amount')) i.value = '0.00';
                    else i.value = '';
                });
                tr.querySelectorAll('input[type=checkbox]').forEach(function(c) { c.checked = false; });
                tr.querySelectorAll('.cp-acc-new-je-acct-name').forEach(function(n) { n.classList.add('d-none'); });
            }
            function bindRow(tr) { bindAccountNameToggle(tr); }
            modalEl.addEventListener('input', function(e) {
                if (!e.target.classList || !e.target.classList.contains('cp-acc-new-je-amount')) return;
                window.cpAccSanitizeAmountTyping(e.target);
                updateTotals();
            });
            modalEl.addEventListener('blur', function(e) {
                if (!e.target.classList || !e.target.classList.contains('cp-acc-new-je-amount')) return;
                window.cpAccFormatAmountBlur(e.target);
                updateTotals();
            }, true);
            debBody.querySelectorAll('tr').forEach(bindRow);
            creBody.querySelectorAll('tr').forEach(bindRow);
            updateTotals();
            modalEl.addEventListener('click', function(e) {
                var b = e.target.closest('[data-cp-acc-je-like-add]');
                if (!b || !modalEl.contains(b)) return;
                var side = b.getAttribute('data-cp-acc-je-like-add');
                var body = side === 'debit' ? debBody : creBody;
                var last = body.querySelector('tr:last-child');
                if (!last) return;
                var nu = last.cloneNode(true);
                resetRow(nu);
                body.appendChild(nu);
                bindRow(nu);
                updateTotals();
            });
            return { updateTotals: updateTotals, resetRow: resetRow, bindRow: bindRow, sumBody: sumBody, debBody: debBody, creBody: creBody };
        }
        function trimJeBodies(debBody, creBody, resetRowFn) {
            while (debBody.querySelectorAll('tr').length > 1) debBody.removeChild(debBody.lastChild);
            while (creBody.querySelectorAll('tr').length > 1) creBody.removeChild(creBody.lastChild);
            debBody.querySelectorAll('tr').forEach(resetRowFn);
            creBody.querySelectorAll('tr').forEach(resetRowFn);
        }
        function setJeSideRows(body, lines, core) {
            var L = (lines && lines.length) ? lines : [{ amount: 0, account_id: 0, account_name: '', cost_center_id: 0, description: '', vat_report: false }];
            while (body.querySelectorAll('tr').length > L.length) body.removeChild(body.lastChild);
            while (body.querySelectorAll('tr').length < L.length) {
                var last0 = body.querySelector('tr:last-child');
                if (!last0) return;
                var nu = last0.cloneNode(true);
                core.resetRow(nu);
                body.appendChild(nu);
                core.bindRow(nu);
            }
            var trList = body.querySelectorAll('tr');
            L.forEach(function(ln, i) {
                var tr = trList[i];
                if (!tr) return;
                ln = ln || {};
                var aid = parseInt(ln.account_id, 10) || 0;
                var sel = tr.querySelector('.cp-acc-new-je-account');
                if (sel) {
                    sel.value = aid ? String(aid) : '';
                    sel.dispatchEvent(new Event('change', { bubbles: true }));
                }
                var nm = tr.querySelector('.cp-acc-new-je-acct-name');
                if (nm) {
                    nm.value = (ln.account_name != null && ln.account_name !== undefined) ? String(ln.account_name) : '';
                    if (!aid && nm.value.trim()) nm.classList.remove('d-none');
                    else nm.classList.add('d-none');
                }
                var cc = tr.querySelector('.cp-acc-new-je-costcenter');
                if (cc) {
                    var ccv = parseInt(ln.cost_center_id, 10) || 0;
                    cc.value = ccv ? String(ccv) : '';
                }
                var ld = tr.querySelector('.cp-acc-new-je-line-desc');
                if (ld) ld.value = (ln.description != null && ln.description !== undefined) ? String(ln.description) : '';
                var vat = tr.querySelector('.cp-acc-new-je-vat');
                if (vat) vat.checked = !!ln.vat_report;
                var am = tr.querySelector('.cp-acc-new-je-amount');
                if (am) am.value = (parseFloat(ln.amount) || 0).toFixed(2);
            });
        }
        function jeLinesHasAmounts(arr) {
            if (!Array.isArray(arr) || !arr.length) return false;
            return arr.some(function(l) { return (parseFloat(l.amount) || 0) > 0; });
        }
        function applyJeLinesToCore(core, parsed, fallbackAmt, fallbackDesc) {
            if (!core) return;
            var useParsed = parsed && typeof parsed === 'object' && (jeLinesHasAmounts(parsed.debit) || jeLinesHasAmounts(parsed.credit));
            if (useParsed) {
                var dL = (parsed.debit && parsed.debit.length) ? parsed.debit : [{ amount: 0 }];
                var cL = (parsed.credit && parsed.credit.length) ? parsed.credit : [{ amount: 0 }];
                setJeSideRows(core.debBody, dL, core);
                setJeSideRows(core.creBody, cL, core);
            } else {
                trimJeBodies(core.debBody, core.creBody, core.resetRow);
                var amt = fallbackAmt || 0;
                var lineD = fallbackDesc || '';
                var dAmt = core.debBody.querySelector('tr .cp-acc-new-je-amount');
                var cAmt = core.creBody.querySelector('tr .cp-acc-new-je-amount');
                if (dAmt) dAmt.value = amt.toFixed(2);
                if (cAmt) cAmt.value = amt.toFixed(2);
                var ld1 = core.debBody.querySelector('tr .cp-acc-new-je-line-desc');
                var ld2 = core.creBody.querySelector('tr .cp-acc-new-je-line-desc');
                if (ld1) ld1.value = lineD;
                if (ld2) ld2.value = lineD;
            }
            core.updateTotals();
        }
        function collectJeLikeLines(debBody, creBody) {
            function side(body) {
                var out = [];
                body.querySelectorAll('tr').forEach(function(tr) {
                    var sel = tr.querySelector('.cp-acc-new-je-account');
                    var aid = sel ? (parseInt(sel.value, 10) || 0) : 0;
                    var nameEl = tr.querySelector('.cp-acc-new-je-acct-name');
                    var aname = nameEl ? nameEl.value.trim() : '';
                    var amt = window.cpAccParseDecimal((tr.querySelector('.cp-acc-new-je-amount') || {}).value);
                    var ld = ((tr.querySelector('.cp-acc-new-je-line-desc') || {}).value || '').trim();
                    var ccSel = tr.querySelector('.cp-acc-new-je-costcenter');
                    var ccid = ccSel ? (parseInt(ccSel.value, 10) || 0) : 0;
                    var vat = !!(tr.querySelector('.cp-acc-new-je-vat') || {}).checked;
                    if (amt <= 0 && !aid && !aname) return;
                    out.push({ account_id: aid, account_name: aname, cost_center_id: ccid, description: ld, vat_report: vat, amount: amt });
                });
                return out;
            }
            return { debit: side(debBody), credit: side(creBody) };
        }
        function validateJeLikeAccounts(debBody, creBody) {
            function check(body) {
                var ok = true;
                body.querySelectorAll('tr').forEach(function(tr) {
                    var amt = window.cpAccParseDecimal((tr.querySelector('.cp-acc-new-je-amount') || {}).value);
                    if (amt <= 0) return;
                    var sel = tr.querySelector('.cp-acc-new-je-account');
                    var aid = sel ? (parseInt(sel.value, 10) || 0) : 0;
                    var aname = ((tr.querySelector('.cp-acc-new-je-acct-name') || {}).value || '').trim();
                    if (!aid && !aname) ok = false;
                });
                return ok;
            }
            return check(debBody) && check(creBody);
        }
        /** One debit + one credit row, equal amounts, no chart account and no free-text name (legacy rows before lines_json). */
        function isPlainLegacyJePair(linesPayload) {
            if (!linesPayload || linesPayload.debit.length !== 1 || linesPayload.credit.length !== 1) return false;
            var d = linesPayload.debit[0];
            var c = linesPayload.credit[0];
            if (!d || !c) return false;
            if (d.account_id || c.account_id) return false;
            if ((d.account_name && d.account_name.trim()) || (c.account_name && c.account_name.trim())) return false;
            if (d.cost_center_id || c.cost_center_id) return false;
            if (d.vat_report || c.vat_report) return false;
            var da = parseFloat(d.amount) || 0;
            var ca = parseFloat(c.amount) || 0;
            if (da <= 0 || Math.abs(da - ca) >= 0.001) return false;
            return true;
        }
        function getYmd(dateId) {
            var dateInp = document.getElementById(dateId);
            if (!dateInp) return '';
            if (dateInp._flatpickr && dateInp._flatpickr.selectedDates.length) {
                var d0 = dateInp._flatpickr.selectedDates[0];
                return d0.getFullYear() + '-' + String(d0.getMonth() + 1).padStart(2, '0') + '-' + String(d0.getDate()).padStart(2, '0');
            }
            return (dateInp.value || '').trim();
        }
        function formatExVoucher(v) {
            v = String(v || '').trim();
            var m = v.match(/^EX-(\d+)$/i) || v.match(/^(?:EXP|EXPENSE|VOUCHER)-?(\d+)$/i);
            if (m) return 'EX-' + String(m[1]).padStart(5, '0');
            return v || '-';
        }
        function formatRcNumberDisp(v) {
            v = String(v || '').trim();
            var m = v.match(/^RC-(\d+)$/i) || v.match(/^(?:REG|RECEIPT|RCP)-?(\d+)$/i);
            if (m) return 'RC-' + String(m[1]).padStart(5, '0');
            return v || '—';
        }

        var receiptModal = document.getElementById('cpAccReceiptJournalModal');
        var rcDeb = document.getElementById('cpAccRcJeDebitBody');
        var rcCre = document.getElementById('cpAccRcJeCreditBody');
        var rcCore = (receiptModal && rcDeb && rcCre)
            ? bindJeLikeCore(receiptModal, rcDeb, rcCre, 'cpAccRcJeTotalDebit', 'cpAccRcJeTotalCredit', 'cpAccRcJeBalanceMsg')
            : null;

        var expenseModal = document.getElementById('cpAccExpenseFormModal');
        var exDeb = document.getElementById('cpAccExJeDebitBody');
        var exCre = document.getElementById('cpAccExJeCreditBody');
        var exCore = (expenseModal && exDeb && exCre)
            ? bindJeLikeCore(expenseModal, exDeb, exCre, 'cpAccExJeTotalDebit', 'cpAccExJeTotalCredit', 'cpAccExJeBalanceMsg')
            : null;

        if (receiptModal && typeof MutationObserver !== 'undefined') {
            new MutationObserver(function() {
                if (receiptModal.classList.contains('is-open')) scheduleJeLikeFp('cpAccRcJeDate');
            }).observe(receiptModal, { attributes: true, attributeFilter: ['class'] });
        }
        if (expenseModal && typeof MutationObserver !== 'undefined') {
            new MutationObserver(function() {
                if (expenseModal.classList.contains('is-open')) scheduleJeLikeFp('cpAccExJeDate');
            }).observe(expenseModal, { attributes: true, attributeFilter: ['class'] });
        }

        function setRcInteractive(enable) {
            if (!receiptModal) return;
            var dateInp = document.getElementById('cpAccRcJeDate');
            if (dateInp && dateInp._flatpickr && !enable) dateInp._flatpickr.destroy();
            receiptModal.querySelectorAll('input,select,textarea,button').forEach(function(el) {
                if (el.id === 'cpAccRcJeCountryId' || el.id === 'cpAccRcJeRecordId' || el.id === 'cpAccRcJeReceiptNumber') return;
                if (el.classList.contains('cp-acc-close')) return;
                if (el.classList.contains('cp-acc-modal-cancel')) return;
                var tag = el.tagName.toLowerCase();
                if (tag === 'button') {
                    if (el.getAttribute('data-cp-acc-je-like-add')) {
                        el.disabled = !enable;
                        el.style.visibility = enable ? '' : 'hidden';
                    }
                    return;
                }
                if (tag === 'select') el.disabled = !enable;
                else {
                    el.readOnly = !enable;
                    el.disabled = !enable;
                }
            });
            var sv = document.getElementById('cpAccRcJeSaveBtn');
            if (sv) sv.style.display = enable ? '' : 'none';
            if (enable && dateInp) setTimeout(function() { scheduleJeLikeFp('cpAccRcJeDate'); }, 0);
        }
        function setExInteractive(enable) {
            if (!expenseModal) return;
            var dateInp = document.getElementById('cpAccExJeDate');
            if (dateInp && dateInp._flatpickr && !enable) dateInp._flatpickr.destroy();
            expenseModal.querySelectorAll('input,select,textarea,button').forEach(function(el) {
                if (el.id === 'cpAccExpenseFormId' || el.id === 'cpAccExJeCountryId' || el.id === 'cpAccExJeVoucherPreview') return;
                if (el.classList.contains('cp-acc-close')) return;
                if (el.classList.contains('cp-acc-modal-cancel')) return;
                var tag = el.tagName.toLowerCase();
                if (tag === 'button') {
                    if (el.getAttribute('data-cp-acc-je-like-add')) {
                        el.disabled = !enable;
                        el.style.visibility = enable ? '' : 'hidden';
                    }
                    return;
                }
                if (tag === 'select') el.disabled = !enable;
                else {
                    el.readOnly = !enable;
                    el.disabled = !enable;
                }
            });
            var sv = document.getElementById('cpAccExpenseFormSaveBtn');
            if (sv) sv.style.display = enable ? '' : 'none';
            if (enable && dateInp) setTimeout(function() { scheduleJeLikeFp('cpAccExJeDate'); }, 0);
        }

        function openReceiptGenericForm(mode, row) {
            if (!receiptModal || !rcCore) return;
            var isEdit = mode === 'edit';
            var isView = mode === 'view';
            var isAdd = mode === 'add';
            receiptModal.setAttribute('data-mode', mode);
            setRcInteractive(!isView);
            var titleEl = document.getElementById('cpAccReceiptJournalTitle');
            if (titleEl) {
                titleEl.innerHTML = '<i class="fas fa-receipt"></i> ' + (isView ? 'View Receipt' : (isEdit ? 'Edit Receipt' : 'New Receipt'));
            }
            var rcSv = document.getElementById('cpAccRcJeSaveBtn');
            if (rcSv) {
                rcSv.disabled = false;
                rcSv.innerHTML = isEdit ? '<i class="fas fa-save me-1"></i>Save Changes' : '<i class="fas fa-save me-1"></i>Save Receipt';
            }
            var rid = document.getElementById('cpAccRcJeRecordId');
            if (rid) rid.value = ((isEdit || isView) && row) ? String(parseInt(row.getAttribute('data-id') || '0', 10) || '') : '';
            var nr = document.getElementById('cpAccRcNumberRow');
            var rn = document.getElementById('cpAccRcJeReceiptNumber');
            if (nr && rn) {
                if (isAdd) {
                    nr.classList.add('d-none');
                } else {
                    nr.classList.remove('d-none');
                    rn.value = row ? formatRcNumberDisp(row.getAttribute('data-receipt-number') || '') : '—';
                }
            }
            var today = (new Date()).toISOString().slice(0, 10);
            var dateInp = document.getElementById('cpAccRcJeDate');
            if (dateInp) {
                dateInp.value = (isEdit || isView) && row ? (row.getAttribute('data-date') || '') : today;
                if (!isView) {
                    dateInp.disabled = false;
                    dateInp.readOnly = false;
                }
            }
            var descEl = document.getElementById('cpAccRcJeDescription');
            if (descEl) descEl.value = (isEdit || isView) && row ? (row.getAttribute('data-description') || '') : '';
            var curEl = document.getElementById('cpAccRcJeCurrency');
            if (curEl) curEl.value = (isEdit || isView) && row ? ((row.getAttribute('data-currency') || 'SAR').trim() || 'SAR') : 'SAR';
            var stEl = document.getElementById('cpAccRcJeStatus');
            if (stEl) {
                var st = (isEdit || isView) && row ? String(row.getAttribute('data-status') || 'completed').toLowerCase() : 'completed';
                if (['completed', 'pending', 'cancelled'].indexOf(st) === -1) st = 'completed';
                stEl.value = st;
            }
            var br = document.getElementById('cpAccRcJeBranch');
            if (br) br.value = 'Main Branch';
            var cu = document.getElementById('cpAccRcJeCustomer');
            if (cu) cu.value = '';
            var amt = 0;
            if ((isEdit || isView) && row) amt = parseFloat(row.getAttribute('data-amount') || '0') || 0;
            var lineD = (isEdit || isView) && row ? (row.getAttribute('data-description') || '') : '';
            var linesParsed = null;
            if (row) {
                var lj = row.getAttribute('data-lines-json');
                if (lj && String(lj).trim()) {
                    try { linesParsed = JSON.parse(lj); } catch (eLj) { linesParsed = null; }
                }
            }
            receiptModal.setAttribute('data-had-saved-lines', (linesParsed && typeof linesParsed === 'object' && (jeLinesHasAmounts(linesParsed.debit) || jeLinesHasAmounts(linesParsed.credit))) ? '1' : '0');
            applyJeLinesToCore(rcCore, linesParsed, amt, lineD);
            receiptModal.classList.add('is-open');
            document.body.style.overflow = 'hidden';
            setTimeout(function() { scheduleJeLikeFp('cpAccRcJeDate'); }, 0);
        }
        window.cpAccOpenReceiptGenericForm = openReceiptGenericForm;

        function openExpenseForm(mode, row) {
            if (!expenseModal || !exCore) return;
            var isEdit = mode === 'edit';
            var isView = mode === 'view';
            expenseModal.setAttribute('data-mode', mode);
            setExInteractive(!isView);
            var titleEl = document.getElementById('cpAccExpenseFormTitle');
            if (titleEl) {
                titleEl.innerHTML = '<i class="fas fa-arrow-down"></i> ' + (isView ? 'View Expense' : (isEdit ? 'Edit Expense' : 'New Expense'));
            }
            var exSv = document.getElementById('cpAccExpenseFormSaveBtn');
            if (exSv) {
                exSv.disabled = false;
                exSv.innerHTML = isEdit ? '<i class="fas fa-save me-1"></i>Save Changes' : '<i class="fas fa-save me-1"></i>Save Expense';
            }
            var eid = document.getElementById('cpAccExpenseFormId');
            if (eid) eid.value = (isEdit || isView) && row ? String(parseInt(row.getAttribute('data-id') || '0', 10) || '') : '';
            var vEl = document.getElementById('cpAccExJeVoucherPreview');
            var vRow = expenseModal.querySelector('.cp-acc-ex-voucher-row');
            if (mode === 'add') {
                if (vRow) vRow.style.display = '';
                if (vEl) vEl.value = 'Auto';
            } else if (row && vEl) {
                if (vRow) vRow.style.display = '';
                vEl.value = formatExVoucher(row.getAttribute('data-voucher-number') || '');
            }
            var today = (new Date()).toISOString().slice(0, 10);
            var dateInp = document.getElementById('cpAccExJeDate');
            if (dateInp) {
                dateInp.value = (isEdit || isView) && row ? (row.getAttribute('data-date') || '') : today;
                if (!isView) {
                    dateInp.disabled = false;
                    dateInp.readOnly = false;
                }
            }
            var descEl = document.getElementById('cpAccExJeDescription');
            if (descEl) descEl.value = (isEdit || isView) && row ? (row.getAttribute('data-description') || '') : '';
            var curEl = document.getElementById('cpAccExJeCurrency');
            if (curEl) curEl.value = (isEdit || isView) && row ? ((row.getAttribute('data-currency') || 'SAR').trim() || 'SAR') : 'SAR';
            var stEl = document.getElementById('cpAccExJeStatus');
            if (stEl) {
                var st = (isEdit || isView) && row ? String(row.getAttribute('data-status') || 'pending').toLowerCase() : 'pending';
                if (['completed', 'pending', 'cancelled'].indexOf(st) === -1) st = 'pending';
                stEl.value = st;
            }
            var br = document.getElementById('cpAccExJeBranch');
            if (br) br.value = 'Main Branch';
            var cu = document.getElementById('cpAccExJeCustomer');
            if (cu) cu.value = '';
            var amt = 0;
            if ((isEdit || isView) && row) amt = parseFloat(row.getAttribute('data-amount') || '0') || 0;
            var lineD = (isEdit || isView) && row ? (row.getAttribute('data-description') || '') : '';
            var exLinesParsed = null;
            if (row) {
                var elj = row.getAttribute('data-lines-json');
                if (elj && String(elj).trim()) {
                    try { exLinesParsed = JSON.parse(elj); } catch (eElj) { exLinesParsed = null; }
                }
            }
            expenseModal.setAttribute('data-had-saved-lines', (exLinesParsed && typeof exLinesParsed === 'object' && (jeLinesHasAmounts(exLinesParsed.debit) || jeLinesHasAmounts(exLinesParsed.credit))) ? '1' : '0');
            applyJeLinesToCore(exCore, exLinesParsed, amt, lineD);
            expenseModal.classList.add('is-open');
            document.body.style.overflow = 'hidden';
            setTimeout(function() { scheduleJeLikeFp('cpAccExJeDate'); }, 0);
            if (mode === 'add' && vEl) {
                fetch(cpAccAccountingApiUrl('/accounting.php?action=next_expense_voucher'), { credentials: 'same-origin', cache: 'no-store' })
                    .then(function(r) { return cpAccAccountingResponseJson(r); })
                    .then(function(res) {
                        if (res && res.success && vEl) vEl.value = formatExVoucher(res.voucher_number || 'EX-00001');
                        else if (vEl) vEl.value = 'EX-00001';
                    })
                    .catch(function() { if (vEl) vEl.value = 'EX-00001'; });
            }
        }
        window.cpAccOpenExpenseForm = openExpenseForm;

        var rcSave = document.getElementById('cpAccRcJeSaveBtn');
        if (rcSave && rcCore && receiptModal) {
            rcSave.addEventListener('click', function() {
                var mode = receiptModal.getAttribute('data-mode') || 'add';
                if (mode === 'view') return;
                var id = parseInt((document.getElementById('cpAccRcJeRecordId') || {}).value || '0', 10) || 0;
                var entryDate = getYmd('cpAccRcJeDate');
                var desc = ((document.getElementById('cpAccRcJeDescription') || {}).value || '').trim();
                var td = rcCore.sumBody(rcCore.debBody);
                var tc = rcCore.sumBody(rcCore.creBody);
                if (!entryDate) { alert('Receipt date is required.'); return; }
                if (!desc) { alert('Description is required.'); return; }
                if (Math.abs(td - tc) >= 0.001) { alert('Total debit and credit must match (balanced entry).'); return; }
                if (td <= 0) { alert('Enter amounts so total debit and credit are greater than zero.'); return; }
                var linesPayload = collectJeLikeLines(rcCore.debBody, rcCore.creBody);
                var hadSavedLines = receiptModal.getAttribute('data-had-saved-lines') === '1';
                var plainLegacy = mode !== 'add' && !hadSavedLines && isPlainLegacyJePair(linesPayload);
                if (!plainLegacy && !validateJeLikeAccounts(rcCore.debBody, rcCore.creBody)) {
                    alert('Each line with an amount needs an account from the chart or a name under “Account name (if not in chart)”.');
                    return;
                }
                var currency = ((document.getElementById('cpAccRcJeCurrency') || {}).value || 'SAR').trim() || 'SAR';
                var status = ((document.getElementById('cpAccRcJeStatus') || {}).value || 'completed').trim().toLowerCase();
                if (['completed', 'pending', 'cancelled'].indexOf(status) === -1) status = 'completed';
                rcSave.disabled = true;
                var payload = (mode === 'edit' && id > 0)
                    ? { _action: 'update_receipt', id: id, receipt_date: entryDate, amount: td, description: desc, currency_code: currency, status: status }
                    : { _module: 'receipts', receipt_date: entryDate, amount: td, description: desc, currency_code: currency, status: status, agency_id: 0, country_id: 0 };
                if (!plainLegacy) payload.lines = linesPayload;
                accJson('/accounting.php', payload).then(function(res) {
                    if (res && res.success) {
                        if (res.receipt_number && window.cpAccShowToast) {
                            window.cpAccShowToast('Receipt saved — ' + formatRcNumberDisp(res.receipt_number));
                        }
                        receiptModal.classList.remove('is-open');
                        if (window.cpAccReloadAndReopenModal) window.cpAccReloadAndReopenModal('cpAccOpenReceiptsModal');
                        else location.reload();
                    } else {
                        alert(res && res.message ? res.message : 'Could not save receipt.');
                    }
                }).catch(function() { alert('Request failed.'); }).finally(function() { rcSave.disabled = false; });
            });
        }

        var exSave = document.getElementById('cpAccExpenseFormSaveBtn');
        if (exSave && exCore && expenseModal) {
            exSave.addEventListener('click', function() {
                var mode = expenseModal.getAttribute('data-mode') || 'add';
                if (mode === 'view') return;
                var id = parseInt((document.getElementById('cpAccExpenseFormId') || {}).value || '0', 10) || 0;
                var expenseDate = getYmd('cpAccExJeDate');
                var desc = ((document.getElementById('cpAccExJeDescription') || {}).value || '').trim();
                var td = exCore.sumBody(exCore.debBody);
                var tc = exCore.sumBody(exCore.creBody);
                if (!expenseDate) { alert('Expense date is required.'); return; }
                if (!desc) { alert('Description is required.'); return; }
                if (Math.abs(td - tc) >= 0.001) { alert('Total debit and credit must match (balanced entry).'); return; }
                if (td <= 0) { alert('Enter amounts so total debit and credit are greater than zero.'); return; }
                var exLinesPayload = collectJeLikeLines(exCore.debBody, exCore.creBody);
                var exHadSaved = expenseModal.getAttribute('data-had-saved-lines') === '1';
                var exPlainLegacy = mode !== 'add' && !exHadSaved && isPlainLegacyJePair(exLinesPayload);
                if (!exPlainLegacy && !validateJeLikeAccounts(exCore.debBody, exCore.creBody)) {
                    alert('Each line with an amount needs an account from the chart or a name under “Account name (if not in chart)”.');
                    return;
                }
                var currency = ((document.getElementById('cpAccExJeCurrency') || {}).value || 'SAR').trim() || 'SAR';
                var status = ((document.getElementById('cpAccExJeStatus') || {}).value || 'pending').trim().toLowerCase();
                if (['completed', 'pending', 'cancelled'].indexOf(status) === -1) status = 'pending';
                exSave.disabled = true;
                var payload = (mode === 'edit' && id > 0)
                    ? { _action: 'update_expense', id: id, expense_date: expenseDate, amount: td, description: desc, currency_code: currency, status: status }
                    : { _module: 'expenses', expense_date: expenseDate, amount: td, description: desc, currency_code: currency, status: status, agency_id: 0, country_id: 0 };
                if (!exPlainLegacy) payload.lines = exLinesPayload;
                accJson('/accounting.php', payload).then(function(res) {
                    if (res && res.success) {
                        if (res.voucher_number && window.cpAccShowToast) {
                            window.cpAccShowToast('Expense saved — ' + formatExVoucher(res.voucher_number));
                        }
                        expenseModal.classList.remove('is-open');
                        if (window.cpAccReloadAndReopenModal) window.cpAccReloadAndReopenModal('cpAccOpenExpensesModal');
                        else location.reload();
                    } else {
                        alert(res && res.message ? res.message : 'Save failed.');
                    }
                }).catch(function() { alert('Request failed.'); }).finally(function() { exSave.disabled = false; });
            });
        }

        var spFormModal = document.getElementById('cpAccSupportPaymentFormModal');
        var spDeb = document.getElementById('cpAccSpJeDebitBody');
        var spCre = document.getElementById('cpAccSpJeCreditBody');
        var spCore = (spFormModal && spDeb && spCre)
            ? bindJeLikeCore(spFormModal, spDeb, spCre, 'cpAccSpJeTotalDebit', 'cpAccSpJeTotalCredit', 'cpAccSpJeBalanceMsg')
            : null;
        if (spFormModal && typeof MutationObserver !== 'undefined') {
            new MutationObserver(function() {
                if (spFormModal.classList.contains('is-open')) scheduleJeLikeFp('cpAccSupportFormDate');
            }).observe(spFormModal, { attributes: true, attributeFilter: ['class'] });
        }
        window.cpAccJeLike = {
            getSupportCore: function() { return spCore; },
            scheduleFp: scheduleJeLikeFp,
            getYmd: getYmd,
            applyJeLinesToCore: applyJeLinesToCore,
            jeLinesHasAmounts: jeLinesHasAmounts,
            collectJeLikeLines: collectJeLikeLines,
            validateJeLikeAccounts: validateJeLikeAccounts,
            isPlainLegacyJePair: isPlainLegacyJePair
        };
    })();

    // Report cards: open Report Viewer modal and show the related table/form
    document.addEventListener('click', function(e){
        var card = e.target.closest('.cp-acc-report-card[data-report-id]');
        if (!card) return;
        e.preventDefault();
        var reportId = card.getAttribute('data-report-id');
        var titleEl = card.querySelector('.title');
        var titleText = titleEl ? titleEl.textContent.trim() : 'Report';
        var viewerModal = document.getElementById('reportViewerModal');
        var titleSpan = document.getElementById('reportViewerTitleText');
        var body = document.getElementById('reportViewerBody');
        if (viewerModal && body && reportId) {
            if (titleSpan) titleSpan.textContent = (window.cpAccReportTitles && window.cpAccReportTitles[reportId]) || titleText;
            body.querySelectorAll('.cp-acc-report-panel').forEach(function(panel){
                panel.classList.toggle('active', panel.id === 'report-' + reportId);
            });
            viewerModal.classList.add('is-open');
            document.body.style.overflow = 'hidden';
            if (window.cpAccBindEnglishFlatpickr) window.cpAccBindEnglishFlatpickr(viewerModal);
            var activeReportPanel = body.querySelector('.cp-acc-report-panel.active');
            if (activeReportPanel) {
                cpAccReportFetchPanelData(activeReportPanel, null).then(function(data) {
                    if (data === null) {
                        cpAccReportApplyPagination(activeReportPanel);
                    }
                });
            }
        }
    });

    // Add Cost Center / Bank Guarantee / Generic form – open forms and save
    (function initAddForms(){
        var apiBase = cpAccControlApiBase();
        function accApi(path, options) {
            options = options || {};
            options.method = options.method || 'GET';
            options.headers = options.headers || {};
            if (options.credentials == null) options.credentials = 'same-origin';
            if (options.cache == null) options.cache = 'no-store';
            if (options.body && typeof options.body !== 'string') {
                options.headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(options.body);
            }
            if (String(options.method || 'GET').toUpperCase() !== 'GET') {
                options.headers = cpAccWithCsrfHeaders(options.headers);
            }
            var url = apiBase + path;
            if (url.indexOf('?') === -1) url += '?control=1'; else url += '&control=1';
            return fetch(url, options).then(function(r){ return r.json(); });
        }
        (function wireWesternAmountFields() {
            var bal = document.getElementById('chartAccountFormBalance');
            if (bal) {
                bal.addEventListener('input', function() { window.cpAccSanitizeAmountTyping(bal); });
                bal.addEventListener('blur', function() { window.cpAccFormatAmountBlur(bal); });
            }
            var bamt = document.getElementById('bankGuaranteeFormAmount');
            if (bamt) {
                bamt.addEventListener('input', function() { window.cpAccSanitizeAmountTyping(bamt); });
                bamt.addEventListener('blur', function() { window.cpAccFormatAmountBlur(bamt); });
            }
        })();
        function cpAccResetCostCenterForm(){
            var idEl = document.getElementById('costCenterFormId');
            var codeEl = document.getElementById('costCenterFormCode');
            var nameEl = document.getElementById('costCenterFormName');
            var descEl = document.getElementById('costCenterFormDescription');
            var activeEl = document.getElementById('costCenterFormActive');
            var titleEl = document.getElementById('costCenterFormTitle');
            if (idEl) idEl.value = '';
            if (codeEl) codeEl.value = '';
            if (nameEl) nameEl.value = '';
            if (descEl) descEl.value = '';
            if (activeEl) activeEl.checked = true;
            if (titleEl) titleEl.textContent = 'Add Cost Center';
        }
        function cpAccOpenCostCenterForm(btn){
            var id = parseInt(btn.getAttribute('data-id') || '0', 10) || 0;
            var code = btn.getAttribute('data-code') || '';
            var name = btn.getAttribute('data-name') || '';
            var desc = btn.getAttribute('data-description') || '';
            var isActive = (btn.getAttribute('data-is-active') || '0') === '1';
            var idEl = document.getElementById('costCenterFormId');
            var codeEl = document.getElementById('costCenterFormCode');
            var nameEl = document.getElementById('costCenterFormName');
            var descEl = document.getElementById('costCenterFormDescription');
            var activeEl = document.getElementById('costCenterFormActive');
            var titleEl = document.getElementById('costCenterFormTitle');
            if (idEl) idEl.value = id > 0 ? String(id) : '';
            if (codeEl) codeEl.value = code;
            if (nameEl) nameEl.value = name;
            if (descEl) descEl.value = desc;
            if (activeEl) activeEl.checked = !!isActive;
            if (titleEl) titleEl.textContent = 'Edit Cost Center';
        }
        var costModal = document.getElementById('costModal');
        var costAddBtn = document.getElementById('costModalAddBtn');
        var costFormModal = document.getElementById('costCenterFormModal');
        if (costAddBtn && costFormModal) costAddBtn.addEventListener('click', function(){
            cpAccResetCostCenterForm();
            costFormModal.classList.add('is-open');
            document.body.style.overflow = 'hidden';
        });
        if (costModal) {
            costModal.addEventListener('click', function(e){
                var btn = e.target.closest('.cp-acc-cost-edit-btn');
                if (!btn) return;
                e.preventDefault();
                cpAccOpenCostCenterForm(btn);
                costFormModal && costFormModal.classList.add('is-open');
                document.body.style.overflow = 'hidden';
            });
        }
        var costSaveBtn = document.getElementById('costCenterFormSaveBtn');
        if (costSaveBtn) costSaveBtn.addEventListener('click', function(){
            var idVal = (document.getElementById('costCenterFormId') || {}).value || '';
            var id = parseInt(idVal, 10) || 0;
            var code = (document.getElementById('costCenterFormCode') || {}).value || '';
            var name = (document.getElementById('costCenterFormName') || {}).value || '';
            var desc = (document.getElementById('costCenterFormDescription') || {}).value || '';
            var isActive = (document.getElementById('costCenterFormActive') || {}).checked ? 1 : 0;
            if (!code.trim()) { alert('Code is required.'); return; }
            if (!name.trim()) { alert('Name is required.'); return; }
            costSaveBtn.disabled = true;
            var isEdit = id > 0;
            var body = isEdit
                ? { _action: 'update_cost_center', id: id, code: code.trim(), name: name.trim(), description: desc.trim(), is_active: isActive }
                : { _module: 'cost_centers', code: code.trim(), name: name.trim(), description: desc.trim(), is_active: isActive, agency_id: 0, country_id: 0 };
            accApi('/accounting.php', { method: 'POST', body: body }).then(function(res){
                if (res && res.success) { window.cpAccReloadAndReopenModal && window.cpAccReloadAndReopenModal('cpAccOpenCostModal'); }
                else alert(res && res.message ? res.message : 'Save failed.');
            }).catch(function(e){ alert(e && e.message ? e.message : 'Request failed.'); }).finally(function(){ costSaveBtn.disabled = false; });
        });

        function cpAccResetBankGuaranteeForm(){
            var idEl = document.getElementById('bankGuaranteeFormId');
            var refEl = document.getElementById('bankGuaranteeFormRef');
            var bankEl = document.getElementById('bankGuaranteeFormBank');
            var amtEl = document.getElementById('bankGuaranteeFormAmount');
            var curEl = document.getElementById('bankGuaranteeFormCurrency');
            var stEl = document.getElementById('bankGuaranteeFormStart');
            var enEl = document.getElementById('bankGuaranteeFormEnd');
            var statusEl = document.getElementById('bankGuaranteeFormStatus');
            var notesEl = document.getElementById('bankGuaranteeFormNotes');
            var titleEl = document.getElementById('bankGuaranteeFormTitle');
            if (idEl) idEl.value = '';
            if (refEl) refEl.value = '';
            if (bankEl) bankEl.value = '';
            if (amtEl) amtEl.value = '0.00';
            if (curEl) curEl.value = 'SAR';
            if (stEl) stEl.value = '';
            if (enEl) enEl.value = '';
            if (statusEl) statusEl.value = 'active';
            if (notesEl) notesEl.value = '';
            if (titleEl) titleEl.textContent = 'Add Bank Guarantee';
        }
        function cpAccSetFpYmd(inputEl, ymd){
            if (!inputEl) return;
            ymd = (ymd || '').trim();
            try {
                if (inputEl._flatpickr && typeof inputEl._flatpickr.setDate === 'function') {
                    if (!ymd) {
                        try { inputEl._flatpickr.clear(); } catch (e0) {}
                        inputEl.value = '';
                        return;
                    }
                    var parts = ymd.split('-');
                    if (parts.length === 3) {
                        var d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
                        inputEl._flatpickr.setDate(d, true);
                        inputEl.value = ymd;
                        return;
                    }
                }
            } catch (e1) {}
            inputEl.value = ymd;
        }

        var bankModal = document.getElementById('bankModal');
        var bankAddBtn = document.getElementById('bankModalAddBtn');
        var bankFormModal = document.getElementById('bankGuaranteeFormModal');
        if (bankAddBtn && bankFormModal) bankAddBtn.addEventListener('click', function(){
            cpAccResetBankGuaranteeForm();
            bankFormModal.classList.add('is-open');
            document.body.style.overflow = 'hidden';
        });
        if (bankModal) {
            bankModal.addEventListener('click', function(e){
                var btn = e.target.closest('.cp-acc-bank-edit-btn');
                if (!btn) return;
                e.preventDefault();
                var id = parseInt(btn.getAttribute('data-id') || '0', 10) || 0;
                var ref = btn.getAttribute('data-reference') || '';
                var bankName = btn.getAttribute('data-bank-name') || '';
                var amount = btn.getAttribute('data-amount') || '0.00';
                var currency = btn.getAttribute('data-currency-code') || 'SAR';
                var startDate = btn.getAttribute('data-start-date') || '';
                var endDate = btn.getAttribute('data-end-date') || '';
                var status = btn.getAttribute('data-status') || 'active';
                var notes = btn.getAttribute('data-notes') || '';
                var idEl = document.getElementById('bankGuaranteeFormId');
                var refEl = document.getElementById('bankGuaranteeFormRef');
                var bankEl = document.getElementById('bankGuaranteeFormBank');
                var amtEl = document.getElementById('bankGuaranteeFormAmount');
                var curEl = document.getElementById('bankGuaranteeFormCurrency');
                var stEl = document.getElementById('bankGuaranteeFormStart');
                var enEl = document.getElementById('bankGuaranteeFormEnd');
                var statusEl = document.getElementById('bankGuaranteeFormStatus');
                var notesEl = document.getElementById('bankGuaranteeFormNotes');
                if (idEl) idEl.value = id > 0 ? String(id) : '';
                if (refEl) refEl.value = ref;
                if (bankEl) bankEl.value = bankName;
                if (amtEl) amtEl.value = amount;
                if (curEl) curEl.value = currency;
                cpAccSetFpYmd(stEl, startDate);
                cpAccSetFpYmd(enEl, endDate);
                if (statusEl) statusEl.value = status;
                if (notesEl) notesEl.value = notes;
                var titleEl = document.getElementById('bankGuaranteeFormTitle');
                if (titleEl) titleEl.textContent = 'Edit Bank Guarantee';
                bankFormModal && bankFormModal.classList.add('is-open');
                document.body.style.overflow = 'hidden';
            });
        }
        var bankSaveBtn = document.getElementById('bankGuaranteeFormSaveBtn');
        if (bankSaveBtn) bankSaveBtn.addEventListener('click', function(){
            var idVal = (document.getElementById('bankGuaranteeFormId') || {}).value || '';
            var id = parseInt(idVal, 10) || 0;
            var ref = (document.getElementById('bankGuaranteeFormRef') || {}).value || '';
            if (!ref.trim()) { alert('Reference is required.'); return; }
            bankSaveBtn.disabled = true;
            var bankName = (document.getElementById('bankGuaranteeFormBank') || {}).value || '';
            var amount = window.cpAccParseDecimal((document.getElementById('bankGuaranteeFormAmount') || {}).value);
            var currency = (document.getElementById('bankGuaranteeFormCurrency') || {}).value || 'SAR';
            var startDate = (document.getElementById('bankGuaranteeFormStart') || {}).value || null;
            var endDate = (document.getElementById('bankGuaranteeFormEnd') || {}).value || null;
            var status = (document.getElementById('bankGuaranteeFormStatus') || {}).value || 'active';
            var notes = (document.getElementById('bankGuaranteeFormNotes') || {}).value || '';

            var isEdit = id > 0;
            var body = isEdit
                ? { _action: 'update_bank_guarantee', id: id, reference: ref.trim(), bank_name: bankName, amount: amount, currency_code: currency, start_date: startDate, end_date: endDate, status: status, notes: notes }
                : { _module: 'bank_guarantees', reference: ref.trim(), bank_name: bankName, amount: amount, currency_code: currency, start_date: startDate, end_date: endDate, status: status, notes: notes, agency_id: 0, country_id: 0 };

            accApi('/accounting.php', { method: 'POST', body: body }).then(function(res){
                if (res && res.success) { window.cpAccReloadAndReopenModal && window.cpAccReloadAndReopenModal('cpAccOpenBankModal'); }
                else alert(res && res.message ? res.message : 'Save failed.');
            }).catch(function(e){ alert(e && e.message ? e.message : 'Request failed.'); }).finally(function(){ bankSaveBtn.disabled = false; });
        });
        var genericModal = document.getElementById('cpAccGenericFormModal');
        var genericTitle = document.getElementById('cpAccGenericFormTitle');
        document.addEventListener('click', function(e){
            var btn = e.target.closest('.cp-acc-open-generic-form');
            if (!btn || !genericModal || !genericTitle) return;
            e.preventDefault();
            var title = btn.getAttribute('data-form-title') || 'New Entry';
            var mod = (btn.getAttribute('data-form-module') || '').trim();
            if (mod === 'receipts') {
                window.cpAccOpenReceiptGenericForm && window.cpAccOpenReceiptGenericForm('add', null);
                return;
            }
            genericTitle.innerHTML = '<i class="fas fa-plus"></i> ' + title;
            var ph = document.getElementById('cpAccGenericFormPlaceholderText');
            if (ph) ph.textContent = 'This form is not available yet.';
            genericModal.classList.add('is-open');
            document.body.style.overflow = 'hidden';
        });
    })();

    (function initReceiptsActions(){
        var receiptsModal = document.getElementById('receiptsModal');
        if (!receiptsModal) return;
        function csvEscapeCell(s) {
            s = String(s || '');
            if (/[",\n\r]/.test(s)) return '"' + s.replace(/"/g, '""') + '"';
            return s;
        }
        function formatRcNumber(v) {
            v = String(v || '').trim();
            var m = v.match(/^RC-(\d+)$/i) || v.match(/^(?:REG|RECEIPT|RCP)-?(\d+)$/i);
            if (m) return 'RC-' + String(m[1]).padStart(5, '0');
            return v || '-';
        }
        function api(path, options) {
            options = options || {};
            options.method = options.method || 'GET';
            options.headers = options.headers || {};
            options.credentials = options.credentials || 'same-origin';
            options.cache = options.cache || 'no-store';
            if (options.body && typeof options.body !== 'string') {
                options.headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(options.body);
            }
            if (String(options.method).toUpperCase() !== 'GET') {
                options.headers = cpAccWithCsrfHeaders(options.headers);
            }
            return fetch(cpAccAccountingApiUrl(path), options).then(function(r){ return cpAccAccountingResponseJson(r); });
        }
        function getSelectedIds() {
            var ids = [];
            receiptsModal.querySelectorAll('.cp-acc-receipt-cb:checked').forEach(function(cb){
                var id = parseInt(cb.value, 10);
                if (id > 0) ids.push(id);
            });
            return ids;
        }
        function updateSelectionInfo() {
            var el = document.getElementById('cpAccReceiptSelectionInfo');
            if (el) el.textContent = getSelectedIds().length + ' selected';
        }
        function applyFilters() {
            var df = (document.getElementById('cpAccReceiptDateFrom') || {}).value || '';
            var dt = (document.getElementById('cpAccReceiptDateTo') || {}).value || '';
            var q = ((document.getElementById('cpAccReceiptSearch') || {}).value || '').trim().toLowerCase();
            var lim = parseInt((document.getElementById('cpAccReceiptPageSize') || {}).value, 10);
            if (!lim || lim < 1) lim = 10;
            var shown = 0;
            receiptsModal.querySelectorAll('tr.cp-acc-receipt-row').forEach(function(tr){
                var ok = true;
                var ed = tr.getAttribute('data-date') || '';
                if (ok && df && ed && ed < df) ok = false;
                if (ok && dt && ed && ed > dt) ok = false;
                if (ok && q) {
                    var txt = '';
                    tr.querySelectorAll('td').forEach(function(td){ txt += ' ' + (td.textContent || ''); });
                    if (txt.toLowerCase().indexOf(q) === -1) ok = false;
                }
                if (!ok) { tr.style.display = 'none'; return; }
                shown++;
                tr.style.display = shown <= lim ? '' : 'none';
            });
            var selAll = document.getElementById('cpAccReceiptSelectAll');
            if (selAll) selAll.checked = false;
            receiptsModal.querySelectorAll('.cp-acc-receipt-cb').forEach(function(cb){ cb.checked = false; });
            updateSelectionInfo();
        }
        function reloadKeepOpen() {
            if (window.cpAccReloadAndReopenModal) window.cpAccReloadAndReopenModal('cpAccOpenReceiptsModal');
            else location.reload();
        }
        var selAll = document.getElementById('cpAccReceiptSelectAll');
        if (selAll) selAll.addEventListener('change', function(){
            receiptsModal.querySelectorAll('tr.cp-acc-receipt-row').forEach(function(tr){
                if (tr.style.display === 'none') return;
                var cb = tr.querySelector('.cp-acc-receipt-cb');
                if (cb) cb.checked = selAll.checked;
            });
            updateSelectionInfo();
        });
        receiptsModal.querySelectorAll('.cp-acc-receipt-cb').forEach(function(cb){
            cb.addEventListener('change', updateSelectionInfo);
        });
        ['cpAccReceiptDateFrom', 'cpAccReceiptDateTo', 'cpAccReceiptSearch', 'cpAccReceiptPageSize'].forEach(function(id){
            var el = document.getElementById(id);
            if (!el) return;
            el.addEventListener(id === 'cpAccReceiptSearch' ? 'input' : 'change', applyFilters);
        });
        var bulkDel = document.getElementById('cpAccReceiptBulkDelete');
        if (bulkDel) bulkDel.addEventListener('click', function(){
            var ids = getSelectedIds();
            if (!ids.length) { alert('Select one or more receipts (checkboxes).'); return; }
            if (!confirm('Delete ' + ids.length + ' selected receipt(s)?')) return;
            api('/accounting.php', { method: 'POST', body: { bulk_delete_module: 'receipts', ids: ids } }).then(function(res){
                if (res && res.success) reloadKeepOpen();
                else alert(res && res.message ? res.message : 'Delete failed.');
            }).catch(function(){ alert('Request failed.'); });
        });
        var bulkExp = document.getElementById('cpAccReceiptBulkExport');
        if (bulkExp) bulkExp.addEventListener('click', function(){
            var ids = getSelectedIds();
            if (!ids.length) { alert('Select one or more receipts (checkboxes).'); return; }
            var lines = [['Receipt #','Date','Amount','Description','Status','Country'].map(csvEscapeCell).join(',')];
            receiptsModal.querySelectorAll('tr.cp-acc-receipt-row').forEach(function(tr){
                var id = parseInt(tr.getAttribute('data-id') || '0', 10);
                if (!id || ids.indexOf(id) === -1) return;
                var cols = tr.querySelectorAll('td');
                var row = [];
                for (var i = 0; i < 6; i++) row.push(csvEscapeCell((cols[i] && cols[i].textContent || '').trim()));
                lines.push(row.join(','));
            });
            var blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8' });
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'receipts-selected.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(a.href);
        });
        receiptsModal.addEventListener('click', function(e){
            var row = e.target.closest('tr.cp-acc-receipt-row');
            if (!row) return;
            var id = parseInt(row.getAttribute('data-id') || '0', 10);
            if (!id) return;
            if (e.target.closest('.cp-acc-receipt-delete')) {
                e.preventDefault();
                if (!confirm('Delete this receipt?')) return;
                api('/accounting.php', { method: 'POST', body: { bulk_delete_module: 'receipts', ids: [id] } }).then(function(res){
                    if (res && res.success) reloadKeepOpen();
                    else alert(res && res.message ? res.message : 'Delete failed.');
                }).catch(function(){ alert('Request failed.'); });
                return;
            }
            if (e.target.closest('.cp-acc-receipt-view')) {
                e.preventDefault();
                if (window.cpAccOpenReceiptGenericForm) window.cpAccOpenReceiptGenericForm('view', row);
                return;
            }
            if (e.target.closest('.cp-acc-receipt-edit')) {
                e.preventDefault();
                if (window.cpAccOpenReceiptGenericForm) window.cpAccOpenReceiptGenericForm('edit', row);
            }
        });
        applyFilters();
    })();

    (function initExpensesActions(){
        var expensesModal = document.getElementById('expensesModal');
        if (!expensesModal) return;
        function csvEscapeCell(s) {
            s = String(s || '');
            if (/[",\n\r]/.test(s)) return '"' + s.replace(/"/g, '""') + '"';
            return s;
        }
        function expenseApi(path, options) {
            options = options || {};
            options.method = options.method || 'GET';
            options.headers = options.headers || {};
            options.credentials = options.credentials || 'same-origin';
            options.cache = options.cache || 'no-store';
            if (options.body && typeof options.body !== 'string') {
                options.headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(options.body);
            }
            if (String(options.method).toUpperCase() !== 'GET') {
                options.headers = cpAccWithCsrfHeaders(options.headers);
            }
            return fetch(cpAccAccountingApiUrl(path), options).then(function(r){ return cpAccAccountingResponseJson(r); });
        }
        function getSelectedExpenseIds() {
            var ids = [];
            expensesModal.querySelectorAll('.cp-acc-expense-cb:checked').forEach(function(cb){
                var id = parseInt(cb.value, 10);
                if (id > 0) ids.push(id);
            });
            return ids;
        }
        function updateExpenseSelectionInfo() {
            var el = document.getElementById('cpAccExpenseSelectionInfo');
            if (el) el.textContent = getSelectedExpenseIds().length + ' selected';
        }
        function applyExpenseFilters() {
            var df = (document.getElementById('cpAccExpenseDateFrom') || {}).value || '';
            var dt = (document.getElementById('cpAccExpenseDateTo') || {}).value || '';
            var q = ((document.getElementById('cpAccExpenseSearch') || {}).value || '').trim().toLowerCase();
            var stF = ((document.getElementById('cpAccExpenseStatusFilter') || {}).value || '').trim().toLowerCase();
            var lim = parseInt((document.getElementById('cpAccExpensePageSize') || {}).value, 10);
            if (!lim || lim < 1) lim = 10;
            var shown = 0;
            expensesModal.querySelectorAll('tr.cp-acc-expense-row').forEach(function(tr){
                var ok = true;
                var ed = tr.getAttribute('data-date') || '';
                if (ok && df && ed && ed < df) ok = false;
                if (ok && dt && ed && ed > dt) ok = false;
                if (ok && stF) {
                    var rowSt = String(tr.getAttribute('data-status') || '').toLowerCase();
                    if (rowSt !== stF) ok = false;
                }
                if (ok && q) {
                    var txt = '';
                    tr.querySelectorAll('td').forEach(function(td){ txt += ' ' + (td.textContent || ''); });
                    if (txt.toLowerCase().indexOf(q) === -1) ok = false;
                }
                if (!ok) { tr.style.display = 'none'; return; }
                shown++;
                tr.style.display = shown <= lim ? '' : 'none';
            });
            var selAll = document.getElementById('cpAccExpenseSelectAll');
            if (selAll) selAll.checked = false;
            expensesModal.querySelectorAll('.cp-acc-expense-cb').forEach(function(cb){ cb.checked = false; });
            updateExpenseSelectionInfo();
        }
        function reloadExpensesKeepOpen() {
            if (window.cpAccReloadAndReopenModal) window.cpAccReloadAndReopenModal('cpAccOpenExpensesModal');
            else location.reload();
        }
        var newBtn = document.getElementById('cpAccExpenseNewBtn');
        if (newBtn) newBtn.addEventListener('click', function(e){
            e.preventDefault();
            if (window.cpAccOpenExpenseForm) window.cpAccOpenExpenseForm('add', null);
        });
        var selAll = document.getElementById('cpAccExpenseSelectAll');
        if (selAll) selAll.addEventListener('change', function(){
            expensesModal.querySelectorAll('tr.cp-acc-expense-row').forEach(function(tr){
                if (tr.style.display === 'none') return;
                var cb = tr.querySelector('.cp-acc-expense-cb');
                if (cb) cb.checked = selAll.checked;
            });
            updateExpenseSelectionInfo();
        });
        expensesModal.querySelectorAll('.cp-acc-expense-cb').forEach(function(cb){
            cb.addEventListener('change', updateExpenseSelectionInfo);
        });
        ['cpAccExpenseDateFrom', 'cpAccExpenseDateTo', 'cpAccExpenseSearch', 'cpAccExpenseStatusFilter', 'cpAccExpensePageSize'].forEach(function(id){
            var el = document.getElementById(id);
            if (!el) return;
            el.addEventListener(id === 'cpAccExpenseSearch' ? 'input' : 'change', applyExpenseFilters);
        });
        var bulkDel = document.getElementById('cpAccExpenseBulkDelete');
        if (bulkDel) bulkDel.addEventListener('click', function(){
            var ids = getSelectedExpenseIds();
            if (!ids.length) { alert('Select one or more expenses (checkboxes).'); return; }
            if (!confirm('Delete ' + ids.length + ' selected expense(s)?')) return;
            expenseApi('/accounting.php', { method: 'POST', body: { bulk_delete_module: 'expenses', ids: ids } }).then(function(res){
                if (res && res.success) reloadExpensesKeepOpen();
                else alert(res && res.message ? res.message : 'Delete failed.');
            }).catch(function(){ alert('Request failed.'); });
        });
        var bulkExp = document.getElementById('cpAccExpenseBulkExport');
        if (bulkExp) bulkExp.addEventListener('click', function(){
            var ids = getSelectedExpenseIds();
            if (!ids.length) { alert('Select one or more expenses (checkboxes).'); return; }
            var lines = [['Voucher #','Date','Amount','Description','Status','Country'].map(csvEscapeCell).join(',')];
            expensesModal.querySelectorAll('tr.cp-acc-expense-row').forEach(function(tr){
                var id = parseInt(tr.getAttribute('data-id') || '0', 10);
                if (!id || ids.indexOf(id) === -1) return;
                var cols = tr.querySelectorAll('td');
                var row = [];
                for (var i = 0; i < 6; i++) row.push(csvEscapeCell((cols[i] && cols[i].textContent || '').trim()));
                lines.push(row.join(','));
            });
            var blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8' });
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'expenses-selected.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(a.href);
        });
        expensesModal.addEventListener('click', function(e){
            var row = e.target.closest('tr.cp-acc-expense-row');
            if (!row) return;
            var id = parseInt(row.getAttribute('data-id') || '0', 10);
            if (!id) return;
            if (e.target.closest('.cp-acc-expense-delete')) {
                e.preventDefault();
                if (!confirm('Delete this expense?')) return;
                expenseApi('/accounting.php', { method: 'POST', body: { bulk_delete_module: 'expenses', ids: [id] } }).then(function(res){
                    if (res && res.success) reloadExpensesKeepOpen();
                    else alert(res && res.message ? res.message : 'Delete failed.');
                }).catch(function(){ alert('Request failed.'); });
                return;
            }
            if (e.target.closest('.cp-acc-expense-view')) {
                e.preventDefault();
                if (window.cpAccOpenExpenseForm) window.cpAccOpenExpenseForm('view', row);
                return;
            }
            if (e.target.closest('.cp-acc-expense-edit')) {
                e.preventDefault();
                if (window.cpAccOpenExpenseForm) window.cpAccOpenExpenseForm('edit', row);
            }
        });
        applyExpenseFilters();
    })();

    (function initSupportPaymentsActions(){
        var supportModal = document.getElementById('supportModal');
        var formModal = document.getElementById('cpAccSupportPaymentFormModal');
        if (!supportModal) return;
        function supportApi(path, options) {
            options = options || {};
            options.method = options.method || 'GET';
            options.headers = options.headers || {};
            options.credentials = options.credentials || 'same-origin';
            options.cache = options.cache || 'no-store';
            if (options.body && typeof options.body !== 'string') {
                options.headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(options.body);
            }
            if (String(options.method).toUpperCase() !== 'GET') {
                options.headers = cpAccWithCsrfHeaders(options.headers);
            }
            return fetch(cpAccAccountingApiUrl(path), options).then(function(r){ return cpAccAccountingResponseJson(r); });
        }
        function csvEscapeCell(s) {
            s = String(s || '');
            if (/[",\n\r]/.test(s)) return '"' + s.replace(/"/g, '""') + '"';
            return s;
        }
        function getSelectedSupportIds() {
            var ids = [];
            supportModal.querySelectorAll('.cp-acc-support-cb:checked').forEach(function(cb){
                var id = parseInt(cb.value, 10);
                if (id > 0) ids.push(id);
            });
            return ids;
        }
        function updateSupportSelectionInfo() {
            var el = document.getElementById('cpAccSupportSelectionInfo');
            if (el) el.textContent = getSelectedSupportIds().length + ' selected';
        }
        function applySupportFilters() {
            var df = (document.getElementById('cpAccSupportDateFrom') || {}).value || '';
            var dt = (document.getElementById('cpAccSupportDateTo') || {}).value || '';
            var q = ((document.getElementById('cpAccSupportSearch') || {}).value || '').trim().toLowerCase();
            var stF = ((document.getElementById('cpAccSupportStatusFilter') || {}).value || '').trim().toLowerCase();
            var lim = parseInt((document.getElementById('cpAccSupportPageSize') || {}).value, 10);
            if (!lim || lim < 1) lim = 10;
            var shown = 0;
            supportModal.querySelectorAll('tr.cp-acc-support-row').forEach(function(tr){
                var ok = true;
                var ed = tr.getAttribute('data-date') || '';
                if (ok && df && ed && ed < df) ok = false;
                if (ok && dt && ed && ed > dt) ok = false;
                if (ok && stF) {
                    var rowSt = String(tr.getAttribute('data-status') || '').toLowerCase();
                    if (rowSt !== stF) ok = false;
                }
                if (ok && q) {
                    var txt = '';
                    tr.querySelectorAll('td').forEach(function(td){ txt += ' ' + (td.textContent || ''); });
                    if (txt.toLowerCase().indexOf(q) === -1) ok = false;
                }
                if (!ok) { tr.style.display = 'none'; return; }
                shown++;
                tr.style.display = shown <= lim ? '' : 'none';
            });
            var selAll = document.getElementById('cpAccSupportSelectAll');
            if (selAll) selAll.checked = false;
            supportModal.querySelectorAll('.cp-acc-support-cb').forEach(function(cb){ cb.checked = false; });
            updateSupportSelectionInfo();
        }
        function reloadSupportKeepOpen() {
            if (window.cpAccReloadAndReopenModal) window.cpAccReloadAndReopenModal('cpAccOpenSupportModal');
            else location.reload();
        }
        function getPageDefaultCountryId() {
            var ac = document.getElementById('accountingContent');
            if (!ac) return 0;
            return parseInt(ac.getAttribute('data-country-id') || '0', 10) || 0;
        }
        function setSupportFormInteractive(enable) {
            if (!formModal) return;
            var dateInp = document.getElementById('cpAccSupportFormDate');
            if (dateInp && dateInp._flatpickr && !enable) dateInp._flatpickr.destroy();
            formModal.querySelectorAll('input,select,textarea,button').forEach(function(el) {
                if (el.id === 'cpAccSupportFormId' || el.id === 'cpAccSupportFormPaymentNumber') return;
                if (el.classList.contains('cp-acc-close')) return;
                if (el.classList.contains('cp-acc-modal-cancel')) return;
                var tag = el.tagName.toLowerCase();
                if (tag === 'button') {
                    if (el.getAttribute('data-cp-acc-je-like-add')) {
                        el.disabled = !enable;
                        el.style.visibility = enable ? '' : 'hidden';
                    }
                    return;
                }
                if (tag === 'select') el.disabled = !enable;
                else {
                    el.readOnly = !enable;
                    el.disabled = !enable;
                }
            });
            var pn = document.getElementById('cpAccSupportFormPaymentNumber');
            if (pn) {
                pn.readOnly = true;
                pn.disabled = !enable;
            }
            var sv = document.getElementById('cpAccSupportFormSaveBtn');
            if (sv) sv.style.display = enable ? '' : 'none';
            if (enable && dateInp && window.cpAccJeLike && window.cpAccJeLike.scheduleFp) {
                setTimeout(function() { window.cpAccJeLike.scheduleFp('cpAccSupportFormDate'); }, 0);
            }
        }
        function openSupportPaymentForm(mode, row) {
            if (!formModal) return;
            var isEdit = mode === 'edit';
            var isView = mode === 'view';
            var isAdd = mode === 'add';
            formModal.setAttribute('data-mode', mode);
            setSupportFormInteractive(!isView);
            var titleEl = document.getElementById('cpAccSupportPaymentFormTitle');
            if (titleEl) {
                titleEl.innerHTML = '<i class="fas fa-hand-holding-usd"></i> ' + (isView ? 'View Support Payment' : (isEdit ? 'Edit Support Payment' : 'New Support Payment'));
            }
            var idEl = document.getElementById('cpAccSupportFormId');
            if (idEl) idEl.value = (isEdit || isView) && row ? String(parseInt(row.getAttribute('data-id') || '0', 10) || '') : '';
            var dateEl = document.getElementById('cpAccSupportFormDate');
            if (dateEl) {
                dateEl.disabled = false;
                dateEl.readOnly = false;
                dateEl.value = (isEdit || isView) && row ? (row.getAttribute('data-date') || '') : (new Date()).toISOString().slice(0, 10);
                if (isView) { dateEl.readOnly = true; dateEl.disabled = true; }
            }
            var cEl = document.getElementById('cpAccSupportFormCountry');
            if (cEl) {
                var cid = (isEdit || isView) && row ? String(parseInt(row.getAttribute('data-country-id') || '0', 10) || '0') : String(getPageDefaultCountryId());
                cEl.value = cid;
            }
            var jl = window.cpAccJeLike;
            var spCore = jl && jl.getSupportCore ? jl.getSupportCore() : null;
            if (spCore && jl.applyJeLinesToCore && jl.jeLinesHasAmounts) {
                var linesParsed = null;
                if (row) {
                    var lj = row.getAttribute('data-lines-json');
                    if (lj && String(lj).trim()) {
                        try { linesParsed = JSON.parse(lj); } catch (eSp) { linesParsed = null; }
                    }
                }
                formModal.setAttribute('data-had-saved-lines', (linesParsed && typeof linesParsed === 'object' && (jl.jeLinesHasAmounts(linesParsed.debit) || jl.jeLinesHasAmounts(linesParsed.credit))) ? '1' : '0');
                var amt = 0;
                if ((isEdit || isView) && row) amt = parseFloat(row.getAttribute('data-amount') || '0') || 0;
                var lineD = (isEdit || isView) && row ? (row.getAttribute('data-description') || '') : '';
                jl.applyJeLinesToCore(spCore, linesParsed, amt, lineD);
            }
            var curEl = document.getElementById('cpAccSupportFormCurrency');
            if (curEl) curEl.value = (isEdit || isView) && row ? ((row.getAttribute('data-currency') || 'SAR').trim() || 'SAR') : 'SAR';
            var pnEl = document.getElementById('cpAccSupportFormPaymentNumber');
            if (pnEl) {
                pnEl.placeholder = 'Assigned on save';
                if (isAdd) {
                    pnEl.value = '';
                    pnEl.placeholder = 'Loading…';
                    supportApi('/accounting.php?action=next_support_payment_number', { method: 'GET' }).then(function(res){
                        if (!pnEl || !formModal.classList.contains('is-open')) return;
                        if (formModal.getAttribute('data-mode') !== 'add') return;
                        if (res && res.success && res.payment_number) {
                            pnEl.value = String(res.payment_number);
                        }
                        pnEl.placeholder = 'Assigned on save';
                    }).catch(function(){
                        if (pnEl && formModal.getAttribute('data-mode') === 'add') pnEl.placeholder = 'Assigned on save';
                    });
                } else {
                    pnEl.value = row ? String(row.getAttribute('data-payment-number') || '').trim() : '';
                }
            }
            var refEl = document.getElementById('cpAccSupportFormReference');
            if (refEl) refEl.value = (isEdit || isView) && row ? (row.getAttribute('data-reference') || '') : '';
            var dEl = document.getElementById('cpAccSupportFormDesc');
            if (dEl) dEl.value = (isEdit || isView) && row ? (row.getAttribute('data-description') || '') : '';
            var stEl = document.getElementById('cpAccSupportFormStatus');
            if (stEl) {
                var s0 = (isEdit || isView) && row ? String(row.getAttribute('data-status') || 'completed').toLowerCase() : 'completed';
                if (['completed', 'pending', 'cancelled'].indexOf(s0) === -1) s0 = 'completed';
                stEl.value = s0;
            }
            var sv = document.getElementById('cpAccSupportFormSaveBtn');
            if (sv) {
                sv.disabled = false;
                sv.innerHTML = isEdit ? '<i class="fas fa-save me-1"></i>Save Changes' : '<i class="fas fa-save me-1"></i>Save';
            }
            formModal.classList.add('is-open');
            document.body.style.overflow = 'hidden';
        }
        window.cpAccOpenSupportPaymentForm = openSupportPaymentForm;
        var newBtn = document.getElementById('cpAccSupportNewBtn');
        if (newBtn) newBtn.addEventListener('click', function(e){
            e.preventDefault();
            openSupportPaymentForm('add', null);
        });
        var selAll = document.getElementById('cpAccSupportSelectAll');
        if (selAll) selAll.addEventListener('change', function(){
            supportModal.querySelectorAll('tr.cp-acc-support-row').forEach(function(tr){
                if (tr.style.display === 'none') return;
                var cb = tr.querySelector('.cp-acc-support-cb');
                if (cb) cb.checked = selAll.checked;
            });
            updateSupportSelectionInfo();
        });
        supportModal.querySelectorAll('.cp-acc-support-cb').forEach(function(cb){
            cb.addEventListener('change', updateSupportSelectionInfo);
        });
        ['cpAccSupportDateFrom', 'cpAccSupportDateTo', 'cpAccSupportSearch', 'cpAccSupportStatusFilter', 'cpAccSupportPageSize'].forEach(function(id){
            var el = document.getElementById(id);
            if (!el) return;
            el.addEventListener(id === 'cpAccSupportSearch' ? 'input' : 'change', applySupportFilters);
        });
        var bulkDel = document.getElementById('cpAccSupportBulkDelete');
        if (bulkDel) bulkDel.addEventListener('click', function(){
            var ids = getSelectedSupportIds();
            if (!ids.length) { alert('Select one or more rows (checkboxes).'); return; }
            if (!confirm('Delete ' + ids.length + ' selected support payment(s)?')) return;
            supportApi('/accounting.php', { method: 'POST', body: { bulk_delete_module: 'support_payments', ids: ids } }).then(function(res){
                if (res && res.success) reloadSupportKeepOpen();
                else alert(res && res.message ? res.message : 'Delete failed.');
            }).catch(function(){ alert('Request failed.'); });
        });
        var bulkExp = document.getElementById('cpAccSupportBulkExport');
        if (bulkExp) bulkExp.addEventListener('click', function(){
            var ids = getSelectedSupportIds();
            if (!ids.length) { alert('Select one or more rows (checkboxes).'); return; }
            var lines = [['Ref #','Date','Country','Amount','Description','Status'].map(csvEscapeCell).join(',')];
            supportModal.querySelectorAll('tr.cp-acc-support-row').forEach(function(tr){
                var id = parseInt(tr.getAttribute('data-id') || '0', 10);
                if (!id || ids.indexOf(id) === -1) return;
                var cols = tr.querySelectorAll('td');
                var row = [];
                for (var i = 0; i < 6; i++) row.push(csvEscapeCell((cols[i] && cols[i].textContent || '').trim()));
                lines.push(row.join(','));
            });
            var blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8' });
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'support-payments-selected.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(a.href);
        });
        var saveBtn = document.getElementById('cpAccSupportFormSaveBtn');
        if (saveBtn && formModal) saveBtn.addEventListener('click', function(){
            var mode = formModal.getAttribute('data-mode') || 'add';
            if (mode === 'view') return;
            var id = parseInt((document.getElementById('cpAccSupportFormId') || {}).value || '0', 10) || 0;
            var jl = window.cpAccJeLike;
            var spCore = jl && jl.getSupportCore ? jl.getSupportCore() : null;
            var gy = jl && jl.getYmd ? jl.getYmd('cpAccSupportFormDate') : ((document.getElementById('cpAccSupportFormDate') || {}).value || '').trim();
            var pdate = gy;
            if (!pdate || !/^\d{4}-\d{2}-\d{2}$/.test(pdate)) { alert('Payment date is required (YYYY-MM-DD).'); return; }
            var description = ((document.getElementById('cpAccSupportFormDesc') || {}).value || '').trim();
            if (!description) { alert('Description is required.'); return; }
            if (!spCore || !jl.collectJeLikeLines || !jl.validateJeLikeAccounts || !jl.isPlainLegacyJePair) {
                alert('Journal lines are not available. Refresh the page.');
                return;
            }
            var td = spCore.sumBody(spCore.debBody);
            var tc = spCore.sumBody(spCore.creBody);
            if (Math.abs(td - tc) >= 0.001) { alert('Total debit and credit must match (balanced entry).'); return; }
            if (td <= 0) { alert('Enter amounts so total debit and credit are greater than zero.'); return; }
            var linesPayload = jl.collectJeLikeLines(spCore.debBody, spCore.creBody);
            var hadSavedLines = formModal.getAttribute('data-had-saved-lines') === '1';
            var plainLegacy = mode !== 'add' && !hadSavedLines && jl.isPlainLegacyJePair(linesPayload);
            if (!plainLegacy && !jl.validateJeLikeAccounts(spCore.debBody, spCore.creBody)) {
                alert('Each line with an amount needs an account from the chart or a name under “Account name (if not in chart)”.');
                return;
            }
            var amount = td;
            var countryId = parseInt((document.getElementById('cpAccSupportFormCountry') || {}).value || '0', 10) || 0;
            var currency = ((document.getElementById('cpAccSupportFormCurrency') || {}).value || 'SAR').trim() || 'SAR';
            var reference = ((document.getElementById('cpAccSupportFormReference') || {}).value || '').trim();
            var status = ((document.getElementById('cpAccSupportFormStatus') || {}).value || 'completed').trim().toLowerCase();
            if (['completed', 'pending', 'cancelled'].indexOf(status) === -1) status = 'completed';
            saveBtn.disabled = true;
            var payload = (mode === 'edit' && id > 0)
                ? { _action: 'update_support_payment', id: id, payment_date: pdate, country_id: countryId, amount: amount, currency_code: currency, reference: reference, description: description, status: status }
                : { _module: 'support_payments', payment_date: pdate, country_id: countryId, amount: amount, currency_code: currency, reference: reference, description: description, status: status, agency_id: 0 };
            if (!plainLegacy) payload.lines = linesPayload;
            supportApi('/accounting.php', { method: 'POST', body: payload }).then(function(res){
                if (res && res.success) {
                    if (res.payment_number && window.cpAccShowToast) window.cpAccShowToast('Support payment saved — ' + String(res.payment_number));
                    closeModal(formModal);
                    reloadSupportKeepOpen();
                } else {
                    alert(res && res.message ? res.message : 'Save failed.');
                }
            }).catch(function(){ alert('Request failed.'); }).finally(function(){ saveBtn.disabled = false; });
        });
        supportModal.addEventListener('click', function(e){
            var row = e.target.closest('tr.cp-acc-support-row');
            if (!row) return;
            var id = parseInt(row.getAttribute('data-id') || '0', 10);
            if (!id) return;
            if (e.target.closest('.cp-acc-support-delete')) {
                e.preventDefault();
                if (!confirm('Delete this support payment?')) return;
                supportApi('/accounting.php', { method: 'POST', body: { bulk_delete_module: 'support_payments', ids: [id] } }).then(function(res){
                    if (res && res.success) reloadSupportKeepOpen();
                    else alert(res && res.message ? res.message : 'Delete failed.');
                }).catch(function(){ alert('Request failed.'); });
                return;
            }
            if (e.target.closest('.cp-acc-support-view')) {
                e.preventDefault();
                openSupportPaymentForm('view', row);
                return;
            }
            if (e.target.closest('.cp-acc-support-edit')) {
                e.preventDefault();
                openSupportPaymentForm('edit', row);
            }
        });
        applySupportFilters();
    })();

    // Chart of Accounts - selection and actions
    (function initChartAccountsActions(){
        var apiBase = cpAccControlApiBase();
        function accApi(path, options) {
            options = options || {};
            options.method = options.method || 'GET';
            options.headers = options.headers || {};
            if (options.credentials == null) options.credentials = 'same-origin';
            if (options.cache == null) options.cache = 'no-store';
            if (options.body && typeof options.body !== 'string') {
                options.headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(options.body);
            }
            if (String(options.method || 'GET').toUpperCase() !== 'GET') {
                options.headers = cpAccWithCsrfHeaders(options.headers);
            }
            var url = apiBase + path;
            if (url.indexOf('?') === -1) url += '?control=1'; else url += '&control=1';
            return fetch(url, options).then(function(r){ return r.json(); });
        }
        var modal = document.getElementById('chartModal');
        if (!modal) return;
        function reloadAndKeepChartOpen() {
            window.cpAccReloadAndReopenModal && window.cpAccReloadAndReopenModal('cpAccOpenChartModal');
        }
        var selectAll = modal.querySelector('#chartSelectAll');
        var checkboxes = Array.prototype.slice.call(modal.querySelectorAll('.chart-select'));
        var selectedInfo = modal.querySelector('#chartSelectedInfo');
        function updateSelectedInfo() {
            var count = checkboxes.filter(function(cb){ return cb.checked; }).length;
            if (selectedInfo) selectedInfo.textContent = count + ' selected';
        }
        if (selectAll) {
            selectAll.addEventListener('change', function(){
                checkboxes.forEach(function(cb){ cb.checked = selectAll.checked; });
                updateSelectedInfo();
            });
        }
        checkboxes.forEach(function(cb){
            cb.addEventListener('change', updateSelectedInfo);
        });
        function getSelectedIds() {
            return checkboxes.filter(function(cb){ return cb.checked; }).map(function(cb){ return parseInt(cb.value, 10); }).filter(function(id){ return id > 0; });
        }
        var formModal = document.getElementById('chartAccountFormModal');
        var formId = document.getElementById('chartAccountFormId');
        var formCode = document.getElementById('chartAccountFormCode');
        var formName = document.getElementById('chartAccountFormName');
        var formType = document.getElementById('chartAccountFormType');
        var formBalance = document.getElementById('chartAccountFormBalance');
        var formCurrency = document.getElementById('chartAccountFormCurrency');
        var formActive = document.getElementById('chartAccountFormActive');
        var formTitleText = document.getElementById('chartAccountFormTitleText');
        var formSaveBtn = document.getElementById('chartAccountFormSaveBtn');
        function openChartAccountForm(editRow) {
            if (editRow) {
                formTitleText.textContent = 'Edit Account';
                formId.value = editRow.getAttribute('data-id') || '';
                formCode.value = (editRow.querySelector('.chart-code') || {}).textContent?.trim() || '';
                formName.value = (editRow.querySelector('.chart-name') || {}).textContent?.trim() || '';
                formType.value = (editRow.querySelector('.chart-type') || {}).textContent?.trim() || 'Asset';
                formBalance.value = window.cpAccParseDecimal(editRow.getAttribute('data-balance') || '0').toFixed(2);
                formCurrency.value = editRow.getAttribute('data-currency') || 'SAR';
                formActive.checked = (editRow.getAttribute('data-active') || '1') === '1';
            } else {
                formTitleText.textContent = 'New Account';
                formId.value = '';
                formCode.value = '';
                formName.value = '';
                formType.value = 'Asset';
                formBalance.value = '0.00';
                formCurrency.value = 'SAR';
                formActive.checked = true;
            }
            if (formModal) formModal.classList.add('is-open');
        }
        var newBtn = modal.querySelector('#chartNewAccountBtn');
        if (newBtn) newBtn.addEventListener('click', function(){ openChartAccountForm(null); });
        if (formSaveBtn) formSaveBtn.addEventListener('click', function(){
            var code = (formCode && formCode.value || '').trim();
            var name = (formName && formName.value || '').trim();
            var type = (formType && formType.value || 'Asset').trim();
            var balance = window.cpAccParseDecimal(formBalance && formBalance.value);
            var currency = (formCurrency && formCurrency.value || 'SAR').trim() || 'SAR';
            var isActive = (formActive && formActive.checked) ? 1 : 0;
            if (!code) { alert('Account code is required.'); return; }
            if (!name) { alert('Account name is required.'); return; }
            var validTypes = ['Asset','Liability','Equity','Income','Expense'];
            if (validTypes.indexOf(type) === -1) { alert('Invalid type.'); return; }
            formSaveBtn.disabled = true;
            var id = (formId && formId.value || '').trim();
            var payload = id ? { _action: 'update_chart_account', id: parseInt(id, 10), account_code: code, account_name: name, account_type: type, balance: balance, currency_code: currency, is_active: isActive }
                : { _module: 'chart_accounts', account_code: code, account_name: name, account_type: type, balance: balance, currency_code: currency, agency_id: 0, country_id: 0, is_active: isActive };
            accApi('/accounting.php', { method: 'POST', body: payload }).then(function(res){
                if (res && res.success) reloadAndKeepChartOpen();
                else alert(res && res.message ? res.message : (id ? 'Update failed.' : 'Could not add account.'));
            }).catch(function(err){ alert(err && err.message ? err.message : 'Request failed.'); }).finally(function(){ formSaveBtn.disabled = false; });
        });
        modal.querySelector('tbody').addEventListener('click', function(e){
            var editBtn = e.target.closest('.chart-edit-btn');
            var delBtn = e.target.closest('.chart-delete-btn');
            var row = e.target.closest('tr[data-id]');
            if (!row) return;
            if (editBtn) { e.preventDefault(); openChartAccountForm(row); return; }
            if (delBtn) {
                e.preventDefault();
                if (!confirm('Delete this account? This cannot be undone.')) return;
                var id = parseInt(row.getAttribute('data-id'), 10);
                if (!id) return;
                accApi('/accounting.php', { method: 'POST', body: { bulk_delete_module: 'chart_accounts', ids: [id] } }).then(function(res){
                    if (res && res.success) reloadAndKeepChartOpen();
                    else alert(res && res.message ? res.message : 'Delete failed.');
                }).catch(function(err){ alert(err && err.message ? err.message : 'Request failed.'); });
            }
        });
        var exportAllBtn = modal.querySelector('#chartExportAllBtn');
        if (exportAllBtn) exportAllBtn.addEventListener('click', function(){
            window.open(apiBase + '/accounting-chart-accounts-export.php?control=1','_blank');
        });
        var bulkExportBtn = modal.querySelector('#chartBulkExportBtn');
        if (bulkExportBtn) bulkExportBtn.addEventListener('click', function(){
            var ids = getSelectedIds();
            if (!ids.length) { alert('Select at least one account.'); return; }
            window.open(apiBase + '/accounting-chart-accounts-export.php?control=1&ids=' + encodeURIComponent(ids.join(',')),'_blank');
        });
        var bulkDeleteBtn = modal.querySelector('#chartBulkDeleteBtn');
        if (bulkDeleteBtn) bulkDeleteBtn.addEventListener('click', function(){
            var ids = getSelectedIds();
            if (!ids.length) { alert('Select at least one account.'); return; }
            if (!confirm('Delete selected accounts? This cannot be undone.')) return;
            accApi('/accounting.php', {method:'POST', body:{bulk_delete_module:'chart_accounts', ids:ids}}).then(function(res){
                if (res && res.success) reloadAndKeepChartOpen(); else alert(res && res.message ? res.message : 'Delete failed.');
            }).catch(function(err){ alert(err && err.message ? err.message : 'Request failed.'); });
        });
        function bulkSetActive(flag) {
            var ids = getSelectedIds();
            if (!ids.length) { alert('Select at least one account.'); return; }
            accApi('/accounting.php?action=chart_accounts_bulk_status', {method:'POST', body:{ids:ids, is_active:flag ? 1 : 0}}).then(function(res){
                if (res && res.success) reloadAndKeepChartOpen(); else alert(res && res.message ? res.message : 'Update failed.');
            }).catch(function(err){ alert(err && err.message ? err.message : 'Request failed.'); });
        }
        var bulkActivateBtn = modal.querySelector('#chartBulkActivateBtn');
        if (bulkActivateBtn) bulkActivateBtn.addEventListener('click', function(){ bulkSetActive(true); });
        var bulkDeactivateBtn = modal.querySelector('#chartBulkDeactivateBtn');
        if (bulkDeactivateBtn) bulkDeactivateBtn.addEventListener('click', function(){ bulkSetActive(false); });
        var filterType = modal.querySelector('#chartFilterType');
        var filterSearch = modal.querySelector('#chartFilterSearch');
        function applyFilters() {
            var typeVal = (filterType && filterType.value) || '';
            var q = (filterSearch && filterSearch.value.toLowerCase()) || '';
            Array.prototype.slice.call(modal.querySelectorAll('tbody tr')).forEach(function(row){
                var code = (row.querySelector('.chart-code') || {}).textContent || '';
                var name = (row.querySelector('.chart-name') || {}).textContent || '';
                var t = (row.querySelector('.chart-type') || {}).textContent || '';
                var okType = !typeVal || t === typeVal;
                var okSearch = !q || code.toLowerCase().indexOf(q) !== -1 || name.toLowerCase().indexOf(q) !== -1;
                row.style.display = (okType && okSearch) ? '' : 'none';
            });
        }
        if (filterType) filterType.addEventListener('change', applyFilters);
        if (filterSearch) filterSearch.addEventListener('input', applyFilters);
    })();

    // Financial report panel: Apply Filters, Clear, Pagination, Export
    function cpAccReportGetFilters(panel) {
        if (!panel) return {};
        var bar = panel.querySelector('.cp-acc-report-filters-bar');
        if (!bar) return {};
        var dates = bar.querySelectorAll('input.cp-acc-fp-en');
        var searchInput = bar.querySelector('input[type="text"]:not(.cp-acc-fp-en)');
        var rawSearch = searchInput ? searchInput.value.trim() : '';
        var showSelect = bar.querySelector('select.cp-acc-report-show-entries') || bar.querySelector('.cp-acc-report-filters-bar select:last-of-type') || bar.querySelector('select');
        return {
            startDate: dates[0] ? dates[0].value : '',
            endDate: dates[1] ? dates[1].value : '',
            asOfDate: dates[0] ? dates[0].value : '',
            rawSearch: rawSearch,
            search: rawSearch.toLowerCase(),
            pageSize: showSelect ? (parseInt(showSelect.value, 10) || 10) : 10
        };
    }
    function cpAccReportApiBase() {
        var b = '';
        try {
            b = cpAccControlApiBase();
        } catch (e) {
            b = '';
        }
        if (b) return b;
        var el = document.getElementById('control-config') || document.getElementById('accountingContent');
        b = el ? String(el.getAttribute('data-api-base') || el.dataset.apiBase || '').replace(/\/$/, '') : '';
        return b || '/api/control';
    }
    function cpAccReportCountryId() {
        var el = document.getElementById('accountingContent');
        if (!el) return 0;
        var v = parseInt(el.getAttribute('data-country-id') || '0', 10);
        return isNaN(v) ? 0 : v;
    }
    function cpAccReportEscapeHtml(s) {
        if (s == null) return '';
        var d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    }
    function cpAccReportDateQueryParams(panel, filters) {
        var dates = panel.querySelectorAll('.cp-acc-report-filters-bar input.cp-acc-fp-en');
        var out = { start_date: '', end_date: '', as_of_date: '' };
        if (dates.length <= 1) {
            var d = (dates[0] && dates[0].value) ? String(dates[0].value).trim() : '';
            out.as_of_date = d;
            if (/^\d{4}-\d{2}-\d{2}$/.test(d)) {
                var parts = d.split('-');
                out.start_date = parts[0] + '-' + parts[1] + '-01';
                out.end_date = d;
            } else {
                out.start_date = d;
                out.end_date = d;
            }
        } else {
            out.start_date = filters.startDate || '';
            out.end_date = filters.endDate || '';
            out.as_of_date = (filters.endDate || filters.startDate || '');
        }
        return out;
    }
    function cpAccReportRenderFromPayload(panel, data) {
        if (!panel || !data) return;
        var periodEl = panel.querySelector('.report-period');
        if (periodEl) {
            periodEl.innerHTML = '<i class="fas fa-calendar-alt"></i> ' + cpAccReportEscapeHtml(data.period_text || '');
        }
        var cardsWrap = panel.querySelector('.cp-acc-report-status-cards');
        if (cardsWrap) {
            var summaryCards = Array.isArray(data.summary_cards) ? data.summary_cards : [];
            cardsWrap.innerHTML = '';
            summaryCards.forEach(function(c) {
                var div = document.createElement('div');
                div.className = 'cp-acc-report-status-card ' + (c.card_class || 'cp-acc-card-blue');
                div.innerHTML = '<span class="val">' + cpAccReportEscapeHtml(c.val) + '</span><span class="lbl">' + cpAccReportEscapeHtml(c.lbl) + '</span>';
                cardsWrap.appendChild(div);
            });
        }
        var table = panel.querySelector('.cp-acc-table');
        if (!table) return;
        var tbl = data.table || {};
        var cols = Array.isArray(tbl.columns) ? tbl.columns : [];
        var nCol = Math.max(1, cols.length);
        var thead = table.querySelector('thead');
        if (thead) {
            thead.innerHTML = '';
            var thr = document.createElement('tr');
            cols.forEach(function(h) {
                var th = document.createElement('th');
                th.textContent = h;
                thr.appendChild(th);
            });
            thead.appendChild(thr);
        }
        var tbody = table.querySelector('tbody');
        if (tbody) {
            tbody.innerHTML = '';
            var rows = Array.isArray(tbl.rows) ? tbl.rows : [];
            var emptyMsg = tbl.empty_message || '';
            if (rows.length === 0) {
                var tr0 = document.createElement('tr');
                var td0 = document.createElement('td');
                td0.colSpan = nCol;
                var wrap = document.createElement('div');
                wrap.className = 'cp-acc-empty';
                wrap.innerHTML = '<i class="fas fa-inbox"></i> ' + cpAccReportEscapeHtml(emptyMsg || 'No data for this period.');
                td0.appendChild(wrap);
                tr0.appendChild(td0);
                tbody.appendChild(tr0);
            } else {
                rows.forEach(function(row) {
                    var tr = document.createElement('tr');
                    if (row.row_class) tr.className = row.row_class;
                    if (row.section_header) {
                        var tds = document.createElement('td');
                        tds.colSpan = row.colspan || nCol;
                        tds.textContent = (row.cells && row.cells[0]) ? row.cells[0] : '';
                        tr.appendChild(tds);
                    } else {
                        (row.cells || []).forEach(function(cell) {
                            var td = document.createElement('td');
                            td.innerHTML = cpAccReportEscapeHtml(cell);
                            tr.appendChild(td);
                        });
                    }
                    tbody.appendChild(tr);
                });
            }
        }
        var footRows = Array.isArray(tbl.footer_rows) ? tbl.footer_rows : [];
        var tfoot = table.querySelector('tfoot');
        if (footRows.length === 0) {
            if (tfoot) {
                tfoot.innerHTML = '';
                if (!tfoot.children.length) {
                    tfoot.remove();
                }
            }
        } else {
            if (!tfoot) {
                tfoot = document.createElement('tfoot');
                table.appendChild(tfoot);
            }
            tfoot.innerHTML = '';
            footRows.forEach(function(fr) {
                var trf = document.createElement('tr');
                trf.className = 'report-totals-row';
                if (Array.isArray(fr)) {
                    fr.forEach(function(c) {
                        var td = document.createElement('td');
                        if (c && typeof c === 'object' && c.text != null) {
                            td.textContent = c.text;
                            if (c.colspan) td.colSpan = c.colspan;
                        } else if (typeof c === 'string') {
                            td.textContent = c;
                        }
                        trf.appendChild(td);
                    });
                }
                tfoot.appendChild(trf);
            });
        }
    }
    function cpAccReportFetchPanelData(panel, triggerBtn) {
        var base = cpAccReportApiBase();
        if (!panel || !panel.id || panel.id.indexOf('report-') !== 0) {
            return Promise.resolve(null);
        }
        var reportId = panel.id.replace('report-', '');
        var filters = cpAccReportGetFilters(panel);
        var dq = cpAccReportDateQueryParams(panel, filters);
        var params = new URLSearchParams();
        params.set('action', 'financial_report_data');
        params.set('report_id', reportId);
        params.set('country_id', String(cpAccReportCountryId()));
        params.set('start_date', dq.start_date);
        params.set('end_date', dq.end_date);
        params.set('as_of_date', dq.as_of_date);
        params.set('search', filters.rawSearch || '');
        params.set('limit', '500');
        var accSel = panel.querySelector('select.cp-acc-report-account-select');
        var glAccSel = panel.querySelector('select.cp-acc-report-gl-account-select');
        if (accSel) {
            params.set('account_id', accSel.value ? String(parseInt(accSel.value, 10) || 0) : '0');
        } else if (glAccSel) {
            params.set('account_id', glAccSel.value ? String(parseInt(glAccSel.value, 10) || 0) : '0');
        }
        var url = base + '/accounting.php?' + params.toString();
        if (url.indexOf('control=1') === -1) {
            url += (url.indexOf('?') >= 0 ? '&' : '?') + 'control=1';
        }
        if (triggerBtn) triggerBtn.disabled = true;
        return fetch(url, { credentials: 'same-origin' })
            .then(function(r) {
                return r.text().then(function(t) {
                    try {
                        return { ok: r.ok, status: r.status, body: JSON.parse(t) };
                    } catch (e) {
                        return { ok: false, status: r.status, body: null, parseError: true };
                    }
                });
            })
            .then(function(res) {
                if (!res || res.parseError || !res.body) {
                    alert('Report request failed or invalid response.');
                    return null;
                }
                if (!res.ok) {
                    alert((res.body && res.body.message) ? res.body.message : ('Report request failed (' + (res.status || '') + ').'));
                    return null;
                }
                var data = res.body;
                if (!data.success) {
                    alert(data.message ? data.message : 'Could not load report.');
                    return null;
                }
                cpAccReportRenderFromPayload(panel, data);
                panel.dataset.reportPage = '1';
                cpAccReportApplyPagination(panel);
                return data;
            })
            .catch(function() {
                alert('Report request failed.');
                return null;
            })
            .finally(function() { if (triggerBtn) triggerBtn.disabled = false; });
    }
    function cpAccReportRowText(tr) {
        var text = '';
        tr.querySelectorAll('td').forEach(function(td){ text += (td.innerText || td.textContent || '').toLowerCase(); });
        return text;
    }
    function cpAccReportApplyPagination(panel) {
        if (!panel) return;
        var table = panel.querySelector('.cp-acc-table');
        var tbody = table ? table.querySelector('tbody') : null;
        if (!tbody) return;
        var filters = cpAccReportGetFilters(panel);
        var search = filters.search;
        var pageSize = Math.max(1, filters.pageSize);
        var currentPage = parseInt(panel.dataset.reportPage || '1', 10);
        var rows = [].slice.call(tbody.querySelectorAll('tr'));
        var matching = rows.filter(function(tr){
            var empty = tr.querySelector('.cp-acc-empty');
            if (empty) return search === '' || cpAccReportRowText(tr).indexOf(search) >= 0;
            return search === '' || cpAccReportRowText(tr).indexOf(search) >= 0;
        });
        var total = matching.length;
        var totalPages = Math.max(1, Math.ceil(total / pageSize));
        currentPage = Math.min(Math.max(1, currentPage), totalPages);
        panel.dataset.reportPage = String(currentPage);
        rows.forEach(function(tr){ tr.style.display = 'none'; });
        matching.slice((currentPage - 1) * pageSize, currentPage * pageSize).forEach(function(tr){ tr.style.display = ''; });
        var start = total === 0 ? 0 : (currentPage - 1) * pageSize + 1;
        var end = Math.min(currentPage * pageSize, total);
        var paginationEl = panel.querySelector('.cp-acc-report-pagination');
        if (paginationEl) {
            var info = paginationEl.querySelector('span');
            if (info) info.textContent = 'Showing ' + start + ' to ' + end + ' of ' + total + ' entries';
            var prevBtn = paginationEl.querySelector('.nav .btn:first-child');
            var nextBtn = paginationEl.querySelector('.nav .btn:last-child');
            var pageBtn = paginationEl.querySelector('.nav .btn.active, .nav .btn:nth-child(2)');
            if (prevBtn) { prevBtn.disabled = currentPage <= 1; prevBtn.onclick = function(){ panel.dataset.reportPage = String(currentPage - 1); cpAccReportApplyPagination(panel); }; }
            if (nextBtn) { nextBtn.disabled = currentPage >= totalPages; nextBtn.onclick = function(){ panel.dataset.reportPage = String(currentPage + 1); cpAccReportApplyPagination(panel); }; }
            if (pageBtn) { pageBtn.textContent = String(currentPage); pageBtn.classList.add('active'); pageBtn.onclick = null; }
        }
    }
    var reportBody = document.getElementById('reportViewerBody');
    if (reportBody) reportBody.addEventListener('click', function(e){
        var panel = document.querySelector('#reportViewerBody .cp-acc-report-panel.active');
        if (!panel) return;
        if (e.target.closest('.cp-acc-report-apply')) {
            e.preventDefault();
            var applyBtn = e.target.closest('.cp-acc-report-apply');
            cpAccReportFetchPanelData(panel, applyBtn);
        } else if (e.target.closest('.cp-acc-report-clear')) {
            e.preventDefault();
            var bar = panel.querySelector('.cp-acc-report-filters-bar');
            if (bar) {
                bar.querySelectorAll('input.cp-acc-fp-en').forEach(function(inp){
                    var def = inp.dataset.default || '';
                    if (inp._flatpickr) { inp._flatpickr.setDate(def || null, false); } else { inp.value = def; }
                });
                var textInput = bar.querySelector('input[type="text"]:not(.cp-acc-fp-en)');
                if (textInput) textInput.value = '';
                bar.querySelectorAll('select').forEach(function(sel){ if (sel.options.length) sel.selectedIndex = 0; });
            }
            panel.dataset.reportPage = '1';
            var rows = panel.querySelectorAll('.cp-acc-table tbody tr');
            rows.forEach(function(tr){ tr.style.display = ''; });
            cpAccReportApplyPagination(panel);
        }
    });
    // Set default date values on panels for Clear
    document.querySelectorAll('.cp-acc-report-panel .cp-acc-report-filters-bar input.cp-acc-fp-en').forEach(function(inp){
        if (!inp.dataset.default) inp.dataset.default = inp.value || '';
    });
    // Export current report table to CSV
    window.cpAccExportReport = function() {
        var panel = document.querySelector('#reportViewerBody .cp-acc-report-panel.active');
        if (!panel) return;
        var table = panel.querySelector('.cp-acc-table');
        if (!table) return;
        var title = (document.getElementById('reportViewerTitleText') || {}).textContent || 'report';
        var rows = table.querySelectorAll('thead tr, tbody tr, tfoot tr');
        var csv = [];
        rows.forEach(function(tr){
            var cells = tr.querySelectorAll('th, td');
            var empty = tr.querySelector('.cp-acc-empty');
            if (empty) return;
            csv.push([].map.call(cells, function(c){ return '"' + (c.innerText || c.textContent || '').replace(/"/g, '""') + '"'; }).join(','));
        });
        var blob = new Blob([csv.join('\r\n')], { type: 'text/csv;charset=utf-8' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'financial-report-' + (title.replace(/\s+/g, '-').toLowerCase()) + '.csv';
        a.click();
        URL.revokeObjectURL(a.href);
    };
    window.cpAccPrintReport = function() { window.print(); };
    window.cpAccRefreshReport = function() {
        var panel = document.querySelector('#reportViewerBody .cp-acc-report-panel.active');
        if (panel) { cpAccReportFetchPanelData(panel, null); }
    };
    window.cpAccSaveFavoriteReport = function() {
        var titleEl = document.getElementById('reportViewerTitleText');
        var title = titleEl ? titleEl.textContent.trim() : '';
        var panel = document.querySelector('#reportViewerBody .cp-acc-report-panel.active');
        var reportId = panel && panel.id ? panel.id.replace('report-', '') : '';
        try {
            var fav = JSON.parse(localStorage.getItem('cpAccReportFavorites') || '[]');
            if (fav.indexOf(reportId) === -1) { fav.push(reportId); localStorage.setItem('cpAccReportFavorites', JSON.stringify(fav)); }
        } catch (e) {}
        cpAccShowToast('Saved "' + title + '" to favorites');
    };
    window.cpAccCompareReport = function() { cpAccShowToast('Compare periods: select two date ranges to compare (coming soon)'); };
    function cpAccShowToast(msg) {
        var el = document.createElement('div');
        el.className = 'cp-acc-report-toast';
        el.textContent = msg;
        document.body.appendChild(el);
        setTimeout(function(){ el.remove(); }, 2500);
    }
    window.cpAccShowToast = cpAccShowToast;
    document.addEventListener('click', function(e){
        var btn = e.target && e.target.closest && e.target.closest('[data-cp-acc-report-action]');
        if (!btn) return;
        var modal = document.getElementById('reportViewerModal');
        if (!modal || !modal.contains(btn)) return;
        e.preventDefault();
        var act = btn.getAttribute('data-cp-acc-report-action');
        if (act === 'save-favorite' && window.cpAccSaveFavoriteReport) window.cpAccSaveFavoriteReport();
        else if (act === 'compare' && window.cpAccCompareReport) window.cpAccCompareReport();
        else if (act === 'print' && window.cpAccPrintReport) window.cpAccPrintReport();
        else if (act === 'export' && window.cpAccExportReport) window.cpAccExportReport();
        else if (act === 'refresh' && window.cpAccRefreshReport) window.cpAccRefreshReport();
    }, true);
    if (reportBody) {
        reportBody.addEventListener('change', function(e){
            var panel = document.querySelector('#reportViewerBody .cp-acc-report-panel.active');
            if (!panel || !e.target.closest('.cp-acc-report-filters-bar select')) return;
            panel.dataset.reportPage = '1';
            cpAccReportApplyPagination(panel);
        });
    }
})();
