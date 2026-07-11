/**
 * RATEB Offline — Ops forms adapter (Phase 14).
 * Per-module hooks: when offline, allowlisted Inv/HR/Proc forms enqueue via existing adapters.
 * Does not finish a generic form-post stub; narrow path matching only.
 */
(function (root) {
    'use strict';

    var DEFAULT_HOOKS = [
        { match: 'stock-movements', module: 'inventory', action: 'stock_movement.create' },
        { match: 'warehouse-transfers', module: 'inventory', action: 'warehouse_transfer.create' },
        { match: 'inventory-audits', module: 'inventory', action: 'stock_count.create' },
        { match: 'hr/attendance/bulk', module: 'hr', action: 'attendance.bulk' },
        { match: 'hr/attendance', module: 'hr', action: 'attendance.create' },
        { match: 'hr/leaves', module: 'hr', action: 'leave_request.draft' },
        { match: 'purchase-requests', module: 'procurement', action: 'purchase_request.draft' },
        { match: 'purchase-orders', module: 'procurement', action: 'purchase_order.draft' },
        { match: 'rfq', module: 'procurement', action: 'rfq.draft' }
    ];

    function cfg() {
        return root.__RATEB_ERP_SHELL_OFFLINE__ || root.__RATEB_ERP_MASTER_DATA__ || {};
    }

    function flags() {
        if (root.RatebOffline && typeof root.RatebOffline.flags === 'function') {
            return root.RatebOffline.flags() || {};
        }
        return cfg().flags || {};
    }

    function isOnline() {
        var conn = root.RatebOfflineConnectivity;
        if (conn && typeof conn.isOnline === 'function') {
            return !!conn.isOnline();
        }
        return typeof navigator === 'undefined' || navigator.onLine !== false;
    }

    function moduleEnabled(module) {
        var f = flags();
        if (!f['offline.enabled']) {
            return false;
        }
        if (module === 'inventory') {
            return !!f['offline.inventory.movements'];
        }
        if (module === 'hr') {
            return !!f['offline.hr.attendance'];
        }
        if (module === 'procurement') {
            return !!f['offline.procurement'];
        }
        return false;
    }

    function formHooks() {
        var list = cfg().ops_form_hooks;
        if (Array.isArray(list) && list.length) {
            return list;
        }
        return DEFAULT_HOOKS;
    }

    function normalizePath(pathname) {
        return String(pathname || '').replace(/\/+$/, '').toLowerCase();
    }

    function matchHook(pathname) {
        var p = normalizePath(pathname);
        var hooks = formHooks();
        // Longer matches first (bulk before attendance).
        var sorted = hooks.slice().sort(function (a, b) {
            return String(b.match || '').length - String(a.match || '').length;
        });
        for (var i = 0; i < sorted.length; i++) {
            var m = String(sorted[i].match || '').replace(/^\/+|\/+$/g, '').toLowerCase();
            if (!m) {
                continue;
            }
            var re = new RegExp('(^|/)' + m.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '(/|$)', 'i');
            if (re.test(p)) {
                return sorted[i];
            }
        }
        return null;
    }

    function formToObject(form) {
        var fd = new FormData(form);
        var out = {};
        fd.forEach(function (value, key) {
            if (key === '_csrf' || key === '_method') {
                return;
            }
            if (Object.prototype.hasOwnProperty.call(out, key)) {
                if (!Array.isArray(out[key])) {
                    out[key] = [out[key]];
                }
                out[key].push(value);
            } else {
                out[key] = value;
            }
        });
        return out;
    }

    function intOrZero(v) {
        var n = parseInt(v, 10);
        return isNaN(n) ? 0 : n;
    }

    function floatOrZero(v) {
        var n = parseFloat(v);
        return isNaN(n) ? 0 : n;
    }

    function buildBulkAttendance(raw) {
        var date = String(raw.attendance_date || '');
        var present = Array.isArray(raw['present[]']) ? raw['present[]'] : (raw.present || []);
        if (!Array.isArray(present) && present) {
            present = [present];
        }
        var rows = [];
        (present || []).forEach(function (eid) {
            var id = intOrZero(eid);
            if (id < 1) {
                return;
            }
            var checkIn = raw['check_in[' + id + ']'] || raw['check_in'] || '';
            var checkOut = raw['check_out[' + id + ']'] || raw['check_out'] || '';
            rows.push({
                employee_id: id,
                check_in: checkIn || null,
                check_out: checkOut || null,
                status: 'present'
            });
        });
        return { attendance_date: date, rows: rows };
    }

    function buildStockCount(raw) {
        var lines = [];
        var invIds = raw['lines'] || raw['inventory_id'] || null;
        if (Array.isArray(raw['line_inventory_id[]']) || raw['line_inventory_id']) {
            var ids = Array.isArray(raw['line_inventory_id[]'])
                ? raw['line_inventory_id[]']
                : [raw['line_inventory_id']];
            var qtys = Array.isArray(raw['line_counted_qty[]'])
                ? raw['line_counted_qty[]']
                : (raw['line_counted_qty'] ? [raw['line_counted_qty']] : []);
            ids.forEach(function (id, idx) {
                lines.push({
                    inventory_id: intOrZero(id),
                    counted_qty: floatOrZero(qtys[idx] != null ? qtys[idx] : 0)
                });
            });
        } else if (raw.inventory_id) {
            lines.push({
                inventory_id: intOrZero(raw.inventory_id),
                counted_qty: floatOrZero(raw.counted_qty != null ? raw.counted_qty : raw.quantity)
            });
        }
        return {
            warehouse_id: intOrZero(raw.warehouse_id) || null,
            notes: raw.notes || null,
            lines: lines
        };
    }

    function buildPayload(hook, raw) {
        var action = String(hook.action || '');
        if (action === 'stock_movement.create') {
            return {
                inventory_id: intOrZero(raw.inventory_id),
                warehouse_id: intOrZero(raw.warehouse_id) || null,
                movement_type: String(raw.movement_type || 'in'),
                quantity: floatOrZero(raw.quantity),
                notes: raw.notes || null
            };
        }
        if (action === 'warehouse_transfer.create') {
            return {
                inventory_id: intOrZero(raw.inventory_id),
                source_warehouse_id: intOrZero(raw.source_warehouse_id),
                destination_warehouse_id: intOrZero(raw.destination_warehouse_id),
                quantity: floatOrZero(raw.quantity),
                notes: raw.notes || null
            };
        }
        if (action === 'stock_count.create') {
            return buildStockCount(raw);
        }
        if (action === 'attendance.create') {
            return {
                employee_id: intOrZero(raw.employee_id),
                attendance_date: String(raw.attendance_date || ''),
                check_in: raw.check_in || null,
                check_out: raw.check_out || null,
                status: raw.status || 'present',
                notes: raw.notes || null
            };
        }
        if (action === 'attendance.bulk') {
            return buildBulkAttendance(raw);
        }
        if (action === 'leave_request.draft') {
            return {
                employee_id: intOrZero(raw.employee_id),
                leave_type_id: intOrZero(raw.leave_type_id) || null,
                start_date: raw.start_date || null,
                end_date: raw.end_date || null,
                days: raw.days != null ? floatOrZero(raw.days) : null,
                notes: raw.notes || null,
                status: 'draft'
            };
        }
        if (action === 'purchase_request.draft'
            || action === 'rfq.draft'
            || action === 'purchase_order.draft') {
            return {
                title: String(raw.title || raw.subject || 'Offline draft'),
                supplier_id: intOrZero(raw.supplier_id) || null,
                department: raw.department || null,
                priority: raw.priority || null,
                notes: raw.notes || null,
                total_estimated: raw.total_estimated != null ? floatOrZero(raw.total_estimated) : null,
                total_amount: raw.total_amount != null ? floatOrZero(raw.total_amount) : null
            };
        }
        return raw;
    }

    function enqueueViaAdapter(hook, payload) {
        var module = String(hook.module || '');
        var action = String(hook.action || '');
        if (module === 'inventory') {
            var inv = root.RatebOfflineInventoryAdapter;
            if (!inv) {
                return Promise.reject(new Error('inventory_adapter_unavailable'));
            }
            if (action === 'stock_movement.create') {
                return inv.enqueueMovement(payload);
            }
            if (action === 'warehouse_transfer.create') {
                return inv.enqueueWarehouseTransfer(payload);
            }
            if (action === 'stock_count.create') {
                return inv.enqueueStockCount(payload);
            }
        }
        if (module === 'hr') {
            var hr = root.RatebOfflineHrAdapter;
            if (!hr) {
                return Promise.reject(new Error('hr_adapter_unavailable'));
            }
            if (action === 'attendance.create') {
                return hr.enqueueAttendance(payload);
            }
            if (action === 'attendance.bulk') {
                return hr.enqueueAttendanceBulk(payload);
            }
            if (action === 'leave_request.draft') {
                return hr.enqueueLeaveDraft(payload);
            }
        }
        if (module === 'procurement') {
            var proc = root.RatebOfflineProcurementAdapter;
            if (!proc) {
                return Promise.reject(new Error('procurement_adapter_unavailable'));
            }
            if (action === 'purchase_request.draft') {
                return proc.enqueuePurchaseRequestDraft(payload);
            }
            if (action === 'rfq.draft') {
                return proc.enqueueRfqDraft(payload);
            }
            if (action === 'purchase_order.draft') {
                return proc.enqueuePurchaseOrderDraft(payload);
            }
        }
        return Promise.reject(new Error('ops_form_action_unsupported'));
    }

    function notify(message, isError) {
        try {
            if (root.RatebOfflineEvents) {
                root.RatebOfflineEvents.emit('ops_form:queued', { message: message, error: !!isError });
            }
        } catch (e) { /* ignore */ }
        try {
            var existing = root.document && root.document.getElementById('rateb-offline-ops-toast');
            if (existing && existing.parentNode) {
                existing.parentNode.removeChild(existing);
            }
            if (!root.document || !root.document.body) {
                return;
            }
            var el = root.document.createElement('div');
            el.id = 'rateb-offline-ops-toast';
            el.setAttribute('role', 'status');
            el.style.cssText = 'position:fixed;bottom:1rem;inset-inline-start:1rem;z-index:9999;'
                + 'padding:.75rem 1rem;border-radius:.5rem;max-width:22rem;font-size:.9rem;'
                + (isError
                    ? 'background:#7f1d1d;color:#fecaca;'
                    : 'background:#14532d;color:#bbf7d0;');
            el.textContent = message;
            root.document.body.appendChild(el);
            setTimeout(function () {
                if (el.parentNode) {
                    el.parentNode.removeChild(el);
                }
            }, 4500);
        } catch (e2) { /* ignore */ }
    }

    function handleSubmit(ev) {
        if (isOnline()) {
            return;
        }
        var form = ev.target;
        if (!form || form.tagName !== 'FORM') {
            return;
        }
        var actionUrl = form.getAttribute('action') || (root.location && root.location.pathname) || '';
        var hook = matchHook(actionUrl) || matchHook(root.location && root.location.pathname);
        if (!hook) {
            return;
        }
        if (!moduleEnabled(hook.module)) {
            return;
        }
        ev.preventDefault();
        ev.stopPropagation();
        var raw = formToObject(form);
        var payload = buildPayload(hook, raw);
        enqueueViaAdapter(hook, payload).then(function (res) {
            var depth = res && (res.queueDepth != null ? res.queueDepth : null);
            notify(
                'تم حفظ العملية في قائمة المزامنة'
                    + (depth != null ? ' (' + depth + ')' : '')
                    + '. Offline queued — sync when online.',
                false
            );
        }).catch(function (err) {
            var msg = (err && err.message) ? String(err.message) : 'queue_failed';
            if (msg === 'client_queue_full') {
                notify('قائمة المزامنة ممتلئة — أعد الاتصال وزامِن أولاً. Queue full.', true);
            } else {
                notify('تعذر الحفظ أوفلاين: ' + msg, true);
            }
        });
    }

    var bound = false;

    function bind() {
        if (bound || !root.document) {
            return;
        }
        var f = flags();
        if (!f['offline.enabled']) {
            return;
        }
        if (!(f['offline.inventory.movements'] || f['offline.hr.attendance'] || f['offline.procurement'])) {
            return;
        }
        root.document.addEventListener('submit', handleSubmit, true);
        bound = true;
    }

    root.RatebOfflineOpsForms = {
        bind: bind,
        matchHook: matchHook,
        buildPayload: buildPayload,
        formToObject: formToObject,
        isModuleEnabled: moduleEnabled,
        DEFAULT_HOOKS: DEFAULT_HOOKS
    };
})(typeof window !== 'undefined' ? window : globalThis);
