/**
 * RATEB Offline — Ops forms adapter (Phase 14 / 14.2 / 15B / 16B / 17B).
 * Per-module hooks: when offline, allowlisted Inv/HR/Proc/Recruitment/Accounting/CRM-draft forms enqueue via existing adapters.
 * Does not finish a generic form-post stub; narrow path matching only.
 * Phase 14.2: purchase-orders/{id}/receive → goods_receipt.receive (flag-gated).
 * Phase 15B: recruitment/candidates create|update|transition (flag-gated).
 * Phase 16B: journal-entries draft create|update + recurring/opening drafts (flag-gated; never post).
 * Phase 17B: crm leads/tasks/meetings/campaigns/contacts/companies drafts (flag-gated).
 * Phase 18B: projects create/update/tasks/timesheets drafts (flag-gated).
 * Phase 19B: eam assets/maintenance/work-orders/inspections drafts (flag-gated).
 * Phase 20B: approvals requests/comments drafts (flag-gated).
 * Phase 21B: eproc suppliers/tenders/contracts drafts (flag-gated).
 * Phase 22B: mfg boms/routings/production/work orders/quality drafts (flag-gated).
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
        { match: 'rfq', module: 'procurement', action: 'rfq.draft' },
        { match: 'recruitment/candidates/create', module: 'recruitment', action: 'candidate.create' },
        { match: 'recruitment/candidates', module: 'recruitment', action: 'candidate.update' },
        { match: 'journal-entries/create', module: 'accounting', action: 'journal.create' },
        { match: 'journal-entries', module: 'accounting', action: 'journal.update' },
        { match: 'accounting/recurring/create', module: 'accounting', action: 'recurring.create' },
        { match: 'accounting/opening-balances', module: 'accounting', action: 'opening_balance.create' },
        { match: 'crm/leads/create', module: 'crm', action: 'lead.create' },
        { match: 'crm/leads', module: 'crm', action: 'lead.update' },
        { match: 'crm/tasks', module: 'crm', action: 'task.create' },
        { match: 'crm/meetings', module: 'crm', action: 'meeting.create' },
        { match: 'crm/campaigns', module: 'crm', action: 'campaign.create' },
        { match: 'crm/contacts', module: 'crm', action: 'contact.create' },
        { match: 'crm/companies', module: 'crm', action: 'company.create' },
        { match: 'projects/create', module: 'projects', action: 'project.create' },
        { match: 'projects/tasks', module: 'projects', action: 'task.create' },
        { match: 'projects/milestones', module: 'projects', action: 'milestone.create' },
        { match: 'projects/issues', module: 'projects', action: 'issue.create' },
        { match: 'projects/risks', module: 'projects', action: 'risk.create' },
        { match: 'projects/timesheets', module: 'projects', action: 'timesheet.create' },
        { match: 'eam/assets/create', module: 'assets', action: 'asset.create' },
        { match: 'eam/assets', module: 'assets', action: 'asset.update' },
        { match: 'eam/requests', module: 'assets', action: 'maintenance_request.create' },
        { match: 'eam/work-orders', module: 'assets', action: 'work_order.create' },
        { match: 'eam/maintenance', module: 'assets', action: 'maintenance_plan.create' },
        { match: 'eam/inspections', module: 'assets', action: 'inspection.create' },
        { match: 'approvals/requests/create', module: 'approval', action: 'approval_request.create' },
        { match: 'approvals/requests', module: 'approval', action: 'approval_request.update' },
        { match: 'eproc/suppliers/create', module: 'procurement_enterprise', action: 'supplier_profile.create' },
        { match: 'eproc/suppliers', module: 'procurement_enterprise', action: 'supplier_profile.update' },
        { match: 'eproc/tenders/create', module: 'procurement_enterprise', action: 'tender.create' },
        { match: 'eproc/contracts/create', module: 'procurement_enterprise', action: 'contract.create' },
        { match: 'eproc/qualification', module: 'procurement_enterprise', action: 'qualification.create' },
        { match: 'eproc/scorecards', module: 'procurement_enterprise', action: 'scorecard.create' },
        { match: 'eproc/portal', module: 'procurement_enterprise', action: 'portal_invite.create' },
        { match: 'mfg/boms/create', module: 'manufacturing', action: 'bom.create' },
        { match: 'mfg/boms', module: 'manufacturing', action: 'bom.update' },
        { match: 'mfg/production-orders/create', module: 'manufacturing', action: 'production_order.create' },
        { match: 'mfg/production-orders', module: 'manufacturing', action: 'production_order.update' },
        { match: 'mfg/work-orders/create', module: 'manufacturing', action: 'work_order.create' },
        { match: 'mfg/work-orders', module: 'manufacturing', action: 'work_order.update' },
        { match: 'mfg/routings', module: 'manufacturing', action: 'routing.create' },
        { match: 'mfg/quality', module: 'manufacturing', action: 'quality_check.create' }
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

    function moduleEnabled(module, action) {
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
            if (!f['offline.procurement']) {
                return false;
            }
            if (action === 'goods_receipt.receive') {
                return !!f['offline.procurement.goods_receipt'];
            }
            return true;
        }
        if (module === 'recruitment') {
            if (!f['offline.recruitment']) {
                return false;
            }
            if (action === 'candidate.create' || action === 'candidate.update' || action === 'note.create') {
                return !!f['offline.recruitment.candidates'];
            }
            if (action === 'workflow.transition') {
                return !!f['offline.recruitment.workflow'];
            }
            if (action === 'assignment.create') {
                return !!f['offline.recruitment.assignment'];
            }
            return true;
        }
        if (module === 'accounting') {
            if (!f['offline.accounting']) {
                return false;
            }
            if (action === 'journal.create' || action === 'journal.update' || action === 'note.create'
                || action === 'recurring.create' || action === 'opening_balance.create') {
                return !!f['offline.accounting.journals'];
            }
            if (action === 'workflow.transition') {
                return !!f['offline.accounting.workflow'];
            }
            return false;
        }
        if (module === 'crm') {
            if (!f['offline.crm']) {
                return false;
            }
            if (action === 'lead.create' || action === 'lead.update' || action === 'note.create'
                || action === 'contact.create' || action === 'company.create') {
                return !!f['offline.crm.leads'];
            }
            if (action === 'workflow.transition') {
                return !!f['offline.crm.workflow'];
            }
            if (action === 'meeting.create' || action === 'call.create' || action === 'task.create') {
                return !!f['offline.crm.activities'];
            }
            return true;
        }
        if (module === 'projects') {
            if (!f['offline.projects']) {
                return false;
            }
            if (action === 'task.create' || action === 'task.update') {
                return !!f['offline.projects.tasks'];
            }
            if (action === 'workflow.transition') {
                return !!f['offline.projects.workflow'];
            }
            if (action === 'timesheet.create') {
                return !!f['offline.projects.timesheets'];
            }
            return true;
        }
        if (module === 'assets') {
            if (!f['offline.assets']) {
                return false;
            }
            if (action === 'maintenance_request.create'
                || action === 'maintenance_plan.create'
                || action === 'work_order.create') {
                return !!f['offline.assets.maintenance'];
            }
            if (action === 'workflow.transition') {
                return !!f['offline.assets.workflow'];
            }
            if (action === 'inspection.create'
                || action === 'checklist.create'
                || action === 'meter_reading.create') {
                return !!f['offline.assets.inspections'];
            }
            return true;
        }
        if (module === 'approval') {
            if (!f['offline.approval']) {
                return false;
            }
            if (action === 'approval_request.create' || action === 'approval_request.update') {
                return !!f['offline.approval.requests'];
            }
            if (action === 'workflow.transition') {
                return !!f['offline.approval.workflow'];
            }
            return true;
        }
        if (module === 'procurement_enterprise') {
            if (!f['offline.procurement_enterprise']) {
                return false;
            }
            if (action === 'supplier_profile.create'
                || action === 'supplier_profile.update'
                || action === 'qualification.create'
                || action === 'qualification.update'
                || action === 'risk.create'
                || action === 'scorecard.create'
                || action === 'portal_invite.create'
                || action === 'collaboration.create') {
                return !!f['offline.procurement_enterprise.suppliers'];
            }
            if (action === 'tender.create' || action === 'bid.create' || action === 'bid_compare.create') {
                return !!f['offline.procurement_enterprise.tenders'];
            }
            if (action === 'contract.create') {
                return !!f['offline.procurement_enterprise.contracts'];
            }
            if (action === 'workflow.transition') {
                return !!f['offline.procurement_enterprise.workflow'];
            }
            return true;
        }
        if (module === 'manufacturing') {
            if (!f['offline.manufacturing']) {
                return false;
            }
            if (action === 'bom.create' || action === 'bom.update'
                || action === 'routing.create' || action === 'routing.update'
                || action === 'production_order.create' || action === 'production_order.update'
                || action === 'work_order.create' || action === 'work_order.update'
                || action === 'material_reservation.create' || action === 'material_consumption.create'
                || action === 'finished_goods.create' || action === 'scrap.create') {
                return !!f['offline.manufacturing.production'];
            }
            if (action === 'workflow.transition') {
                return !!f['offline.manufacturing.workflow'];
            }
            if (action === 'quality_check.create') {
                return !!f['offline.manufacturing.quality'];
            }
            return true;
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

    function isPurchaseOrderReceivePath(pathname) {
        return /purchase-orders\/\d+\/receive(\/|$|\?)/i.test(String(pathname || ''));
    }

    function extractPoIdFromPath(pathname) {
        var m = String(pathname || '').match(/purchase-orders\/(\d+)\/receive/i);
        return m ? (parseInt(m[1], 10) || 0) : 0;
    }

    function isRecruitmentTransitionPath(pathname) {
        return /recruitment\/candidates\/\d+\/transition(\/|$|\?)/i.test(String(pathname || ''));
    }

    function extractCandidateIdFromPath(pathname) {
        var m = String(pathname || '').match(/recruitment\/candidates\/(\d+)/i);
        return m ? (parseInt(m[1], 10) || 0) : 0;
    }

    function isAccountingLifecyclePath(pathname) {
        return /journal-entries\/\d+\/lifecycle(\/|$|\?)/i.test(String(pathname || ''));
    }

    function isAccountingPostPath(pathname) {
        return /journal-entries\/\d+\/(post|void|reject|submit-approval)(\/|$|\?)/i.test(String(pathname || ''))
            || /journal-entries\/bulk-(approve|reject|void)/i.test(String(pathname || ''));
    }

    function extractJournalIdFromPath(pathname) {
        var m = String(pathname || '').match(/journal-entries\/(\d+)/i);
        return m ? (parseInt(m[1], 10) || 0) : 0;
    }

    function matchHook(pathname) {
        var p = normalizePath(pathname);
        if (isAccountingPostPath(p)) {
            return null;
        }
        var hooks = formHooks();
        // Longer matches first (bulk before attendance; create before candidates).
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
                var hook = sorted[i];
                if (String(hook.match).indexOf('purchase-orders') >= 0 && isPurchaseOrderReceivePath(p)) {
                    return {
                        match: hook.match,
                        module: 'procurement',
                        action: 'goods_receipt.receive'
                    };
                }
                if (String(hook.match).indexOf('recruitment/candidates') >= 0 && isRecruitmentTransitionPath(p)) {
                    return {
                        match: hook.match,
                        module: 'recruitment',
                        action: 'workflow.transition'
                    };
                }
                if (String(hook.match).indexOf('journal-entries') >= 0 && isAccountingLifecyclePath(p)) {
                    return {
                        match: hook.match,
                        module: 'accounting',
                        action: 'workflow.transition'
                    };
                }
                return hook;
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

    function buildGoodsReceipt(raw, pathname) {
        var receiveQtys = {};
        Object.keys(raw || {}).forEach(function (key) {
            var m = String(key).match(/^receive_qty\[(\d+)\]$/);
            if (m) {
                receiveQtys[m[1]] = floatOrZero(raw[key]);
            }
        });
        if (raw.receive_qty && typeof raw.receive_qty === 'object' && !Array.isArray(raw.receive_qty)) {
            Object.keys(raw.receive_qty).forEach(function (k) {
                receiveQtys[k] = floatOrZero(raw.receive_qty[k]);
            });
        }
        return {
            purchase_order_id: intOrZero(raw.purchase_order_id || raw.order_id)
                || extractPoIdFromPath(pathname),
            warehouse_id: intOrZero(raw.warehouse_id) || null,
            receive_qty: receiveQtys
        };
    }

    function buildPayload(hook, raw, pathname) {
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
        if (action === 'goods_receipt.receive') {
            return buildGoodsReceipt(raw, pathname || '');
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
        if (action === 'candidate.create' || action === 'candidate.update') {
            var cand = {
                full_name: String(raw.full_name || raw.name || ''),
                nationality: raw.nationality || null,
                phone: raw.phone || null,
                email: raw.email || null,
                agency_id: intOrZero(raw.agency_id) || null,
                notes: raw.notes || null,
                expected_status: raw.expected_status || raw.expected_workflow_status || null
            };
            if (action === 'candidate.update') {
                cand.candidate_id = intOrZero(raw.candidate_id || raw.id)
                    || extractCandidateIdFromPath(pathname || '');
            }
            return cand;
        }
        if (action === 'workflow.transition') {
            if (String(hook.module || '') === 'accounting') {
                return {
                    journal_entry_id: intOrZero(raw.journal_entry_id || raw.entry_id || raw.id)
                        || extractJournalIdFromPath(pathname || ''),
                    to_status: String(raw.to_status || raw.target_status || raw.workflow_status || ''),
                    reason: raw.reason || null,
                    expected_status: raw.expected_status || null
                };
            }
            return {
                candidate_id: intOrZero(raw.candidate_id)
                    || extractCandidateIdFromPath(pathname || ''),
                to_status: String(raw.to_status || raw.workflow_status || ''),
                reason: raw.reason || null
            };
        }
        if (action === 'journal.create' || action === 'journal.update') {
            var journal = {
                entry_date: raw.entry_date || null,
                description: raw.description || null,
                description_ar: raw.description_ar || null,
                currency_code: raw.currency_code || null,
                notes: raw.notes || null,
                lines: raw.lines || null,
                expected_status: raw.expected_status || 'draft'
            };
            if (action === 'journal.update') {
                journal.journal_entry_id = intOrZero(raw.journal_entry_id || raw.entry_id || raw.id)
                    || extractJournalIdFromPath(pathname || '');
            }
            return journal;
        }
        if (action === 'recurring.create') {
            return {
                name: String(raw.name || raw.title || 'Offline recurring'),
                frequency: raw.frequency || null,
                start_date: raw.start_date || null,
                notes: raw.notes || null
            };
        }
        if (action === 'opening_balance.create') {
            return {
                fiscal_period_id: intOrZero(raw.fiscal_period_id) || null,
                account_id: intOrZero(raw.account_id) || null,
                debit: raw.debit != null ? floatOrZero(raw.debit) : null,
                credit: raw.credit != null ? floatOrZero(raw.credit) : null,
                notes: raw.notes || null
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
            if (action === 'goods_receipt.receive') {
                return proc.enqueueGoodsReceipt(payload);
            }
        }
        if (module === 'recruitment') {
            var rec = root.RatebOfflineRecruitmentAdapter;
            if (!rec) {
                return Promise.reject(new Error('recruitment_adapter_unavailable'));
            }
            if (action === 'candidate.create') {
                return rec.enqueueCandidateCreate(payload);
            }
            if (action === 'candidate.update') {
                return rec.enqueueCandidateUpdate(payload);
            }
            if (action === 'workflow.transition') {
                return rec.enqueueWorkflowTransition(payload);
            }
            if (action === 'assignment.create') {
                return rec.enqueueAssignmentCreate(payload);
            }
            if (typeof rec.enqueue === 'function') {
                return rec.enqueue(action, payload);
            }
        }
        if (module === 'accounting') {
            var acc = root.RatebOfflineAccountingAdapter;
            if (!acc) {
                return Promise.reject(new Error('accounting_adapter_unavailable'));
            }
            if (action === 'journal.create') {
                return acc.enqueueJournalCreate(payload);
            }
            if (action === 'journal.update') {
                return acc.enqueueJournalUpdate(payload);
            }
            if (action === 'workflow.transition') {
                return acc.enqueueWorkflowTransition(payload);
            }
            if (action === 'recurring.create') {
                return acc.enqueueRecurringCreate(payload);
            }
            if (action === 'opening_balance.create') {
                return acc.enqueueOpeningBalanceCreate(payload);
            }
            if (action === 'note.create') {
                return acc.enqueueNoteCreate(payload);
            }
            if (typeof acc.enqueue === 'function') {
                return acc.enqueue(action, payload);
            }
        }
        if (module === 'crm') {
            var crm = root.RatebOfflineCrmAdapter;
            if (!crm) {
                return Promise.reject(new Error('crm_adapter_unavailable'));
            }
            if (action === 'lead.create') {
                return crm.enqueueLeadCreate(payload);
            }
            if (action === 'lead.update') {
                return crm.enqueueLeadUpdate(payload);
            }
            if (action === 'workflow.transition') {
                return crm.enqueueWorkflowTransition(payload);
            }
            if (action === 'meeting.create') {
                return crm.enqueueMeetingCreate(payload);
            }
            if (action === 'task.create') {
                return crm.enqueueTaskCreate(payload);
            }
            if (action === 'campaign.create') {
                return crm.enqueueCampaignCreate(payload);
            }
            if (action === 'contact.create') {
                return crm.enqueueContactCreate(payload);
            }
            if (action === 'company.create') {
                return crm.enqueueCompanyCreate(payload);
            }
            if (typeof crm.enqueue === 'function') {
                return crm.enqueue(action, payload);
            }
        }
        if (module === 'projects') {
            var prj = root.RatebOfflineProjectsAdapter;
            if (!prj) {
                return Promise.reject(new Error('projects_adapter_unavailable'));
            }
            if (action === 'project.create') {
                return prj.enqueueProjectCreate(payload);
            }
            if (action === 'project.update') {
                return prj.enqueueProjectUpdate(payload);
            }
            if (action === 'task.create') {
                return prj.enqueueTaskCreate(payload);
            }
            if (action === 'task.update') {
                return prj.enqueueTaskUpdate(payload);
            }
            if (action === 'workflow.transition') {
                return prj.enqueueWorkflowTransition(payload);
            }
            if (action === 'milestone.create') {
                return prj.enqueueMilestoneCreate(payload);
            }
            if (action === 'timesheet.create') {
                return prj.enqueueTimesheetCreate(payload);
            }
            if (action === 'issue.create') {
                return prj.enqueueIssueCreate(payload);
            }
            if (action === 'risk.create') {
                return prj.enqueueRiskCreate(payload);
            }
            if (typeof prj.enqueue === 'function') {
                return prj.enqueue(action, payload);
            }
        }
        if (module === 'assets') {
            var eam = root.RatebOfflineAssetsAdapter;
            if (!eam) {
                return Promise.reject(new Error('assets_adapter_unavailable'));
            }
            if (action === 'asset.create') {
                return eam.enqueueAssetCreate(payload);
            }
            if (action === 'asset.update') {
                return eam.enqueueAssetUpdate(payload);
            }
            if (action === 'workflow.transition') {
                return eam.enqueueWorkflowTransition(payload);
            }
            if (action === 'maintenance_request.create') {
                return eam.enqueueMaintenanceRequestCreate(payload);
            }
            if (action === 'maintenance_plan.create') {
                return eam.enqueueMaintenancePlanCreate(payload);
            }
            if (action === 'work_order.create') {
                return eam.enqueueWorkOrderCreate(payload);
            }
            if (action === 'inspection.create') {
                return eam.enqueueInspectionCreate(payload);
            }
            if (typeof eam.enqueue === 'function') {
                return eam.enqueue(action, payload);
            }
        }
        if (module === 'approval') {
            var eap = root.RatebOfflineApprovalAdapter;
            if (!eap) {
                return Promise.reject(new Error('approval_adapter_unavailable'));
            }
            if (action === 'approval_request.create') {
                return eap.enqueueRequestCreate(payload);
            }
            if (action === 'approval_request.update') {
                return eap.enqueueRequestUpdate(payload);
            }
            if (action === 'workflow.transition') {
                return eap.enqueueWorkflowTransition(payload);
            }
            if (action === 'comment.create') {
                return eap.enqueueCommentCreate(payload);
            }
            if (action === 'delegation.create') {
                return eap.enqueueDelegationCreate(payload);
            }
            if (typeof eap.enqueue === 'function') {
                return eap.enqueue(action, payload);
            }
        }
        if (module === 'procurement_enterprise') {
            var eproc = root.RatebOfflineProcurementEnterpriseAdapter;
            if (!eproc) {
                return Promise.reject(new Error('procurement_enterprise_adapter_unavailable'));
            }
            if (action === 'supplier_profile.create') {
                return eproc.enqueueSupplierProfileCreate(payload);
            }
            if (action === 'supplier_profile.update') {
                return eproc.enqueueSupplierProfileUpdate(payload);
            }
            if (action === 'qualification.create') {
                return eproc.enqueueQualificationCreate(payload);
            }
            if (action === 'tender.create') {
                return eproc.enqueueTenderCreate(payload);
            }
            if (action === 'contract.create') {
                return eproc.enqueueContractCreate(payload);
            }
            if (action === 'scorecard.create') {
                return eproc.enqueueScorecardCreate(payload);
            }
            if (action === 'portal_invite.create') {
                return eproc.enqueuePortalInviteCreate(payload);
            }
            if (action === 'workflow.transition') {
                return eproc.enqueueWorkflowTransition(payload);
            }
            if (typeof eproc.enqueue === 'function') {
                return eproc.enqueue(action, payload);
            }
        }
        if (module === 'manufacturing') {
            var mfg = root.RatebOfflineManufacturingAdapter;
            if (!mfg) {
                return Promise.reject(new Error('manufacturing_adapter_unavailable'));
            }
            if (action === 'bom.create') {
                return mfg.enqueueBomCreate(payload);
            }
            if (action === 'bom.update') {
                return mfg.enqueueBomUpdate(payload);
            }
            if (action === 'routing.create') {
                return mfg.enqueueRoutingCreate(payload);
            }
            if (action === 'routing.update') {
                return mfg.enqueueRoutingUpdate(payload);
            }
            if (action === 'production_order.create') {
                return mfg.enqueueProductionOrderCreate(payload);
            }
            if (action === 'production_order.update') {
                return mfg.enqueueProductionOrderUpdate(payload);
            }
            if (action === 'work_order.create') {
                return mfg.enqueueWorkOrderCreate(payload);
            }
            if (action === 'work_order.update') {
                return mfg.enqueueWorkOrderUpdate(payload);
            }
            if (action === 'quality_check.create') {
                return mfg.enqueueQualityCheckCreate(payload);
            }
            if (action === 'workflow.transition') {
                return mfg.enqueueWorkflowTransition(payload);
            }
            if (typeof mfg.enqueue === 'function') {
                return mfg.enqueue(action, payload);
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
        if (!moduleEnabled(hook.module, hook.action)) {
            return;
        }
        ev.preventDefault();
        ev.stopPropagation();
        var raw = formToObject(form);
        var payload = buildPayload(hook, raw, actionUrl);
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
        if (!(f['offline.inventory.movements']
            || f['offline.hr.attendance']
            || f['offline.procurement']
            || f['offline.recruitment']
            || f['offline.accounting']
            || f['offline.crm']
            || f['offline.projects']
            || f['offline.assets']
            || f['offline.approval']
            || f['offline.procurement_enterprise']
            || f['offline.manufacturing'])) {
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
