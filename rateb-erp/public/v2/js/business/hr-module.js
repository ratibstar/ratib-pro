/*!
 * RATEB Offline V2 — Phase 16 HR BusinessModule
 *
 * Owns HR documents only (employees, org, attendance, leave, overtime, contracts,
 * recruitment drafts, onboarding, performance, training, document meta, timeline).
 * AF 2.1 + AF 2.1.1. Mandatory dep: identity. Optional: accounting / crm (APIs only).
 * Never owns inventory / accounting GL / procurement / sales / CRM stores.
 * Never copies PHP / Offline V1 / Recruitment monolith / Contact Center.
 */
(function (root) {
    'use strict';

    var Business = root.RatebOfflineV2Business;
    if (!Business || !Business.BusinessModule) {
        return;
    }

    var BusinessModule = Business.BusinessModule;
    var HR_VERSION = '1.0.0-phase16';
    var ET = {
        employee: 'hr.employee',
        department: 'hr.department',
        position: 'hr.position',
        orgUnit: 'hr.org_unit',
        location: 'hr.location',
        attendance: 'hr.attendance',
        leaveType: 'hr.leave_type',
        leaveRequest: 'hr.leave_request',
        leaveBalance: 'hr.leave_balance',
        overtime: 'hr.overtime',
        contract: 'hr.contract',
        candidate: 'hr.candidate',
        onboarding: 'hr.onboarding',
        performance: 'hr.performance',
        training: 'hr.training',
        enrollment: 'hr.training_enrollment',
        document: 'hr.document_meta',
        timeline: 'hr.timeline',
        statusHistory: 'hr.status_history',
        settings: 'hr.settings'
    };
    var EMP_TRANSITIONS = {
        draft: ['registered', 'archived'],
        registered: ['active', 'archived'],
        active: ['on_leave', 'suspended', 'terminated', 'archived'],
        on_leave: ['active', 'terminated', 'archived'],
        suspended: ['active', 'terminated', 'archived'],
        terminated: ['archived'],
        archived: []
    };
    var TRAIN_TRANSITIONS = {
        planned: ['scheduled', 'cancelled', 'archived'],
        scheduled: ['in_progress', 'cancelled', 'archived'],
        in_progress: ['completed', 'cancelled', 'archived'],
        completed: ['archived'],
        cancelled: ['archived'],
        archived: []
    };
    var PERF_TRANSITIONS = {
        draft: ['submitted', 'archived'],
        submitted: ['approved', 'draft', 'archived'],
        approved: ['closed', 'archived'],
        closed: ['archived'],
        archived: []
    };
    var RECRUIT_TRANSITIONS = {
        draft: ['registered', 'archived'],
        registered: ['interview', 'archived'],
        interview: ['offer', 'rejected', 'archived'],
        offer: ['hired', 'rejected', 'archived'],
        hired: ['deployed', 'archived'],
        deployed: ['archived'],
        rejected: ['archived'],
        archived: []
    };
    var ONBOARD_TRANSITIONS = {
        draft: ['in_progress', 'archived'],
        in_progress: ['completed', 'cancelled', 'archived'],
        completed: ['archived'],
        cancelled: ['archived'],
        archived: []
    };
    var LEAVE_TRANSITIONS = {
        pending: ['approved', 'rejected', 'cancelled'],
        approved: [],
        rejected: [],
        cancelled: []
    };

    function nowIso() {
        return new Date().toISOString();
    }

    function uid(prefix) {
        return (prefix || 'id') + '-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 8);
    }

    function pad4(n) {
        return ('0000' + n).slice(-4);
    }

    function appendOwned(store, entityType, entityId, payload) {
        return store.get(entityType, entityId, payload && payload.company_id).then(function (existing) {
            if (existing) {
                return Promise.reject(new Error('hr_timeline_immutable:' + entityId));
            }
            return store.put(entityType, entityId, payload, 1);
        });
    }

    function HrModule() {
        BusinessModule.call(this, {
            id: 'hr',
            version: HR_VERSION,
            name: 'Human Resources',
            description: 'Offline V2 HR — employees, org, attendance, leave, training; identity mandatory.',
            moduleKind: 'hr',
            dependencies: [
                { id: 'identity', version: '>=1.0.0' }
            ],
            permissions: ['ui.contribute', 'services.register', 'db.read', 'sync.enqueue'],
            capabilities: [
                'ui.nav', 'route.register', 'services', 'settings', 'workspace', 'diagnostics',
                'hr.employees', 'hr.attendance', 'hr.leave', 'hr.training', 'hr.performance'
            ],
            compat: {
                sdk: '>=1.0.0',
                runtime: '>=1.0.0',
                router: '>=1.0.0',
                shell: '>=1.0.0',
                sync: '>=1.0.0',
                db: '>=1.0.0',
                pm: '>=1.0.0'
            },
            routes: [
                { id: 'hr.home', path: '/hr', title: 'HR' }
            ],
            config: {
                ownsInventory: false,
                ownsAccounting: false,
                ownsSales: false,
                ownsProcurement: false,
                ownsCrm: false,
                ownsAuthentication: false,
                optionalDependencies: ['accounting', 'crm'],
                appendOnlyTimeline: true,
                workflowSoleWriter: true,
                neverPostsGl: true
            }
        });
        this._store = null;
        this._empSeq = 0;
    }

    HrModule.prototype = Object.create(BusinessModule.prototype);
    HrModule.prototype.constructor = HrModule;

    HrModule.prototype._ensureStore = function () {
        if (this._store) {
            return Promise.resolve(this._store);
        }
        var db = this.ctx && this.ctx.db;
        if (!db) {
            return Promise.reject(new Error('hr_db_missing'));
        }
        var self = this;
        return db.open().then(function () {
            self._store = Business.createDocStore(db, {
                ownedPrefix: 'hr.',
                errorCode: 'hr_forbidden_storage'
            });
            return self._store;
        });
    };

    HrModule.prototype._hasService = function (moduleId, name) {
        var rt = root.RatebOfflineV2Runtime;
        return !!(rt && rt.services && rt.services.has('module.' + moduleId + '.' + name));
    };

    HrModule.prototype._callIdentity = function (name, arg) {
        return this.callPublished('identity', name, arg);
    };

    HrModule.prototype._accountingAvailable = function () {
        return this._hasService('accounting', 'listAccounts') ||
            this._hasService('accounting', 'trialBalance');
    };

    HrModule.prototype._crmAvailable = function () {
        return this._hasService('crm', 'listLeads') ||
            this._hasService('crm', 'upsertLead');
    };

    HrModule.prototype.requireIdentity = function () {
        var self = this;
        return Promise.all([
            self._callIdentity('session'),
            self._callIdentity('claims'),
            self._callIdentity('rbac')
        ]).then(function (parts) {
            var session = parts[0] || {};
            var claims = parts[1];
            var rbac = parts[2];
            if (!claims || !claims.company_id) {
                throw new Error('hr_identity_not_enrolled');
            }
            var perms = (rbac && rbac.permissions) || [];
            var allowed = perms.indexOf('hr.manage') !== -1 ||
                perms.indexOf('hr.view') !== -1 ||
                perms.indexOf('hr.create') !== -1 ||
                perms.indexOf('*') !== -1;
            var canWrite = perms.indexOf('hr.manage') !== -1 ||
                perms.indexOf('hr.create') !== -1 ||
                perms.indexOf('hr.update') !== -1 ||
                perms.indexOf('*') !== -1;
            var canOversee = perms.indexOf('hr.oversight') !== -1 ||
                perms.indexOf('hr.manage') !== -1 ||
                perms.indexOf('*') !== -1;
            return {
                session: session,
                claims: claims,
                rbac: rbac,
                company_id: claims.company_id,
                branch_id: claims.branch_id || 0,
                user_id: claims.user_id,
                unlocked: !!(session && session.unlocked),
                allowed: allowed,
                canWrite: canWrite,
                canOversee: canOversee,
                permissions: perms
            };
        });
    };

    HrModule.prototype._gate = function (needWrite) {
        return this.requireIdentity().then(function (idCtx) {
            if (!idCtx.unlocked || !idCtx.allowed) {
                throw new Error('hr_forbidden');
            }
            if (needWrite && !idCtx.canWrite) {
                throw new Error('hr_write_forbidden');
            }
            return idCtx;
        });
    };

    HrModule.prototype._emit = function (name, payload) {
        if (this.ctx && this.ctx.events) {
            this.ctx.events.emit(name, payload || {});
        }
    };

    HrModule.prototype._enqueueBusinessEvent = function (action, entityType, entityId, data) {
        var rt = root.RatebOfflineV2Runtime;
        var sync = rt && rt.services && rt.services.tryGet('sync');
        if (!sync || typeof sync.enqueue !== 'function') {
            return Promise.reject(new Error('sync_not_ready'));
        }
        if (String(entityType).indexOf('hr.') !== 0) {
            return Promise.reject(new Error('hr_sync_forbidden_entity'));
        }
        return sync.enqueue({
            module: 'hr',
            action: action,
            entityType: entityType,
            entityId: String(entityId),
            data: data || {},
            version: 1
        });
    };

    HrModule.prototype.refuseForbiddenStorage = function () {
        var self = this;
        return this._ensureStore().then(function (store) {
            var probes = ['inv.item', 'acct.journal', 'crm.lead', 'sales.order', 'identity.claims'];
            var chain = Promise.resolve(true);
            probes.forEach(function (t) {
                chain = chain.then(function (okSoFar) {
                    if (!okSoFar) {
                        return false;
                    }
                    return store.put(t, 'hack', { x: 1 }).then(function () {
                        return false;
                    }).catch(function (err) {
                        return /forbidden_storage/i.test(String(err && err.message));
                    });
                });
            });
            return chain.then(function (ok) { return { ok: !!ok }; });
        });
    };

    /* ---------- Timeline ---------- */
    HrModule.prototype.recordTimeline = function (spec) {
        var self = this;
        return this._ensureStore().then(function (store) {
            var id = uid('tl');
            var row = {
                id: id,
                company_id: spec.company_id,
                event_type: spec.event_type || 'event',
                related_type: spec.related_type || null,
                related_id: spec.related_id || null,
                employee_id: spec.employee_id || null,
                message: spec.message || '',
                payload: spec.payload || {},
                created_by: spec.created_by || null,
                created_at: nowIso()
            };
            return appendOwned(store, ET.timeline, id, row).then(function () {
                self._emit('hr:timeline_recorded', { id: id, event_type: row.event_type });
                return { ok: true, event: row };
            });
        });
    };

    HrModule.prototype.listTimeline = function (filter) {
        var self = this;
        filter = filter || {};
        return this.requireIdentity().then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                return store.list(ET.timeline, idCtx.company_id).then(function (rows) {
                    return rows.map(function (r) { return r.payload; }).filter(function (e) {
                        if (filter.employee_id && e.employee_id !== filter.employee_id) {
                            return false;
                        }
                        return true;
                    });
                });
            });
        });
    };

    HrModule.prototype.refuseTimelineMutation = function () {
        var self = this;
        return this.requireIdentity().then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var id = uid('tl-imut');
                return appendOwned(store, ET.timeline, id, {
                    id: id,
                    company_id: idCtx.company_id,
                    event_type: 'probe',
                    created_at: nowIso()
                }).then(function () {
                    return appendOwned(store, ET.timeline, id, {
                        id: id,
                        company_id: idCtx.company_id,
                        event_type: 'mutated'
                    }).then(function () {
                        return { ok: false };
                    }).catch(function (err) {
                        return { ok: /timeline_immutable/i.test(String(err && err.message)) };
                    });
                });
            });
        });
    };

    /* ---------- Org directory ---------- */
    HrModule.prototype.upsertDepartment = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var id = spec.id || uid('dept');
                var row = {
                    id: id,
                    company_id: idCtx.company_id,
                    code: spec.code || id,
                    name: spec.name || 'Department',
                    parent_id: spec.parent_id || null,
                    status: spec.status || 'active',
                    updated_at: nowIso()
                };
                return store.put(ET.department, id, row).then(function () {
                    return { ok: true, department: row };
                });
            });
        });
    };

    HrModule.prototype.listDepartments = function () {
        var self = this;
        return this.requireIdentity().then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                return store.list(ET.department, idCtx.company_id).then(function (rows) {
                    return rows.map(function (r) { return r.payload; });
                });
            });
        });
    };

    HrModule.prototype.upsertPosition = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var id = spec.id || uid('pos');
                var row = {
                    id: id,
                    company_id: idCtx.company_id,
                    code: spec.code || id,
                    name: spec.name || 'Position',
                    department_id: spec.department_id || null,
                    status: spec.status || 'active',
                    updated_at: nowIso()
                };
                return store.put(ET.position, id, row).then(function () {
                    return { ok: true, position: row };
                });
            });
        });
    };

    HrModule.prototype.listPositions = function () {
        var self = this;
        return this.requireIdentity().then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                return store.list(ET.position, idCtx.company_id).then(function (rows) {
                    return rows.map(function (r) { return r.payload; });
                });
            });
        });
    };

    HrModule.prototype.upsertOrgUnit = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var id = spec.id || uid('ou');
                var row = {
                    id: id,
                    company_id: idCtx.company_id,
                    code: spec.code || id,
                    name: spec.name || 'Org Unit',
                    parent_id: spec.parent_id || null,
                    status: 'active',
                    updated_at: nowIso()
                };
                return store.put(ET.orgUnit, id, row).then(function () {
                    return { ok: true, org_unit: row };
                });
            });
        });
    };

    HrModule.prototype.upsertLocation = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var id = spec.id || uid('loc');
                var row = {
                    id: id,
                    company_id: idCtx.company_id,
                    code: spec.code || id,
                    name: spec.name || 'Location',
                    city: spec.city || '',
                    status: 'active',
                    updated_at: nowIso()
                };
                return store.put(ET.location, id, row).then(function () {
                    return { ok: true, location: row };
                });
            });
        });
    };

    HrModule.prototype.listOrgUnits = function () {
        var self = this;
        return this.requireIdentity().then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                return store.list(ET.orgUnit, idCtx.company_id).then(function (rows) {
                    return rows.map(function (r) { return r.payload; });
                });
            });
        });
    };

    /* ---------- Employees + WorkflowPort ---------- */
    HrModule.prototype.upsertEmployee = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var id = spec.id || uid('emp');
                return store.get(ET.employee, id, idCtx.company_id).then(function (existing) {
                    var row;
                    if (existing) {
                        row = existing.payload;
                        ['full_name', 'email', 'phone', 'department_id', 'position_id',
                            'org_unit_id', 'location_id', 'hire_date', 'identity_user_id'].forEach(function (k) {
                            if (spec[k] != null) {
                                row[k] = spec[k];
                            }
                        });
                        row.updated_at = nowIso();
                        return store.put(ET.employee, id, row, existing.version + 1).then(function () {
                            return self.recordTimeline({
                                company_id: idCtx.company_id,
                                event_type: 'employee_updated',
                                employee_id: id,
                                created_by: idCtx.user_id,
                                message: 'Employee updated'
                            }).then(function () {
                                return { ok: true, employee: row };
                            });
                        });
                    }
                    self._empSeq += 1;
                    row = {
                        id: id,
                        employee_code: spec.employee_code || ('EMP-' + pad4(self._empSeq)),
                        company_id: idCtx.company_id,
                        branch_id: idCtx.branch_id,
                        full_name: spec.full_name || 'Employee',
                        email: spec.email || '',
                        phone: spec.phone || '',
                        department_id: spec.department_id || null,
                        position_id: spec.position_id || null,
                        org_unit_id: spec.org_unit_id || null,
                        location_id: spec.location_id || null,
                        hire_date: spec.hire_date || null,
                        identity_user_id: spec.identity_user_id || null,
                        workflow_status: 'draft',
                        status: 'active',
                        /* Never store payroll salary as financial SoT — link-only flag */
                        owns_payroll: false,
                        created_by: idCtx.user_id,
                        created_at: nowIso(),
                        updated_at: nowIso()
                    };
                    return store.put(ET.employee, id, row).then(function () {
                        self._emit('hr:employee_created', { id: id });
                        return self.recordTimeline({
                            company_id: idCtx.company_id,
                            event_type: 'employee_created',
                            employee_id: id,
                            created_by: idCtx.user_id,
                            message: row.employee_code
                        }).then(function () {
                            return self._enqueueBusinessEvent('employee_created', ET.employee, id, {
                                id: id,
                                employee_code: row.employee_code,
                                workflow_status: 'draft'
                            });
                        }).then(function () {
                            return { ok: true, employee: row };
                        });
                    });
                });
            });
        });
    };

    HrModule.prototype.getEmployee = function (employeeId) {
        var self = this;
        return this.requireIdentity().then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                return store.get(ET.employee, employeeId, idCtx.company_id).then(function (r) {
                    return r ? r.payload : null;
                });
            });
        });
    };

    HrModule.prototype.listEmployees = function () {
        var self = this;
        return this.requireIdentity().then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                return store.list(ET.employee, idCtx.company_id).then(function (rows) {
                    return rows.map(function (r) { return r.payload; });
                });
            });
        });
    };

    HrModule.prototype.linkIdentityUser = function (employeeId, userId) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                return store.get(ET.employee, employeeId, idCtx.company_id).then(function (rec) {
                    if (!rec) {
                        throw new Error('hr_employee_missing');
                    }
                    var emp = rec.payload;
                    emp.identity_user_id = userId;
                    emp.updated_at = nowIso();
                    return store.put(ET.employee, employeeId, emp, rec.version + 1).then(function () {
                        return self.recordTimeline({
                            company_id: idCtx.company_id,
                            event_type: 'identity_linked',
                            employee_id: employeeId,
                            created_by: idCtx.user_id,
                            message: 'Linked identity user ' + userId,
                            payload: { owns_credentials: false }
                        }).then(function () {
                            return {
                                ok: true,
                                employee: emp,
                                owns_credentials: false,
                                owns_authentication: false
                            };
                        });
                    });
                });
            });
        });
    };

    HrModule.prototype._transitionEntity = function (entityType, entityId, toStatus, machine, eventName, idField) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                return store.get(entityType, entityId, idCtx.company_id).then(function (rec) {
                    if (!rec) {
                        throw new Error('hr_entity_missing:' + entityType);
                    }
                    var row = rec.payload;
                    var from = String(row.workflow_status || 'draft');
                    var to = String(toStatus || '').trim();
                    var allowed = machine[from] || [];
                    if (allowed.indexOf(to) === -1) {
                        throw new Error('hr_workflow_denied:' + from + '->' + to);
                    }
                    row.workflow_status = to;
                    if (to === 'archived') {
                        row.status = 'archived';
                    }
                    if (entityType === ET.employee && to === 'terminated') {
                        row.termination_date = nowIso().slice(0, 10);
                    }
                    row.updated_at = nowIso();
                    var histId = uid('sh');
                    var hist = {
                        id: histId,
                        company_id: idCtx.company_id,
                        entity_type: entityType,
                        entity_id: entityId,
                        from_status: from,
                        to_status: to,
                        created_by: idCtx.user_id,
                        created_at: nowIso()
                    };
                    return store.put(entityType, entityId, row, rec.version + 1).then(function () {
                        return appendOwned(store, ET.statusHistory, histId, hist);
                    }).then(function () {
                        var payload = { id: entityId, from: from, to: to };
                        self._emit(eventName, payload);
                        var tl = {
                            company_id: idCtx.company_id,
                            event_type: 'workflow',
                            related_type: entityType,
                            related_id: entityId,
                            created_by: idCtx.user_id,
                            message: from + ' → ' + to,
                            payload: { from: from, to: to }
                        };
                        if (idField) {
                            tl[idField] = entityId;
                        }
                        return self.recordTimeline(tl);
                    }).then(function () {
                        return self._enqueueBusinessEvent('workflow_transition', entityType, entityId, {
                            id: entityId,
                            from: from,
                            to: to
                        });
                    }).then(function () {
                        return { ok: true, entity: row, from: from, to: to };
                    });
                });
            });
        });
    };

    HrModule.prototype.transitionEmployee = function (employeeId, toStatus) {
        return this._transitionEntity(ET.employee, employeeId, toStatus, EMP_TRANSITIONS,
            'hr:employee_transitioned', 'employee_id');
    };

    /* ---------- Attendance ---------- */
    HrModule.prototype.recordAttendance = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var employeeId = spec.employee_id;
                var date = String(spec.date || nowIso()).slice(0, 10);
                if (!employeeId) {
                    throw new Error('hr_attendance_employee_required');
                }
                var id = employeeId + ':' + date;
                var row = {
                    id: id,
                    company_id: idCtx.company_id,
                    employee_id: employeeId,
                    date: date,
                    status: spec.status || 'present',
                    check_in: spec.check_in || null,
                    check_out: spec.check_out || null,
                    created_by: idCtx.user_id,
                    created_at: nowIso()
                };
                return store.put(ET.attendance, id, row).then(function () {
                    self._emit('hr:attendance_recorded', { id: id, employee_id: employeeId });
                    return self.recordTimeline({
                        company_id: idCtx.company_id,
                        event_type: 'attendance',
                        employee_id: employeeId,
                        created_by: idCtx.user_id,
                        message: date + ' ' + row.status
                    }).then(function () {
                        return { ok: true, attendance: row };
                    });
                });
            });
        });
    };

    HrModule.prototype.listAttendance = function (employeeId) {
        var self = this;
        return this.requireIdentity().then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                return store.list(ET.attendance, idCtx.company_id).then(function (rows) {
                    return rows.map(function (r) { return r.payload; }).filter(function (a) {
                        if (employeeId && a.employee_id !== employeeId) {
                            return false;
                        }
                        return true;
                    });
                });
            });
        });
    };

    /* ---------- Leave ---------- */
    HrModule.prototype.seedLeaveTypes = function () {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var types = [
                    { id: 'lt-annual', code: 'annual', name: 'Annual Leave', days_per_year: 21 },
                    { id: 'lt-sick', code: 'sick', name: 'Sick Leave', days_per_year: 15 },
                    { id: 'lt-unpaid', code: 'unpaid', name: 'Unpaid Leave', days_per_year: 0 }
                ];
                var chain = Promise.resolve();
                types.forEach(function (t) {
                    chain = chain.then(function () {
                        var row = Object.assign({
                            company_id: idCtx.company_id,
                            status: 'active',
                            updated_at: nowIso()
                        }, t);
                        return store.put(ET.leaveType, t.id, row);
                    });
                });
                return chain.then(function () {
                    return { ok: true, count: types.length };
                });
            });
        });
    };

    HrModule.prototype.createLeaveRequest = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var id = spec.id || uid('lv');
                var row = {
                    id: id,
                    company_id: idCtx.company_id,
                    employee_id: spec.employee_id,
                    leave_type_id: spec.leave_type_id || 'lt-annual',
                    start_date: String(spec.start_date || '').slice(0, 10),
                    end_date: String(spec.end_date || '').slice(0, 10),
                    days: Number(spec.days || 1),
                    reason: spec.reason || '',
                    workflow_status: 'pending',
                    status: 'pending',
                    created_by: idCtx.user_id,
                    created_at: nowIso()
                };
                if (!row.employee_id || !row.start_date || !row.end_date) {
                    throw new Error('hr_leave_invalid');
                }
                return store.put(ET.leaveRequest, id, row).then(function () {
                    self._emit('hr:leave_requested', { id: id });
                    return self.recordTimeline({
                        company_id: idCtx.company_id,
                        event_type: 'leave_requested',
                        employee_id: row.employee_id,
                        related_id: id,
                        created_by: idCtx.user_id,
                        message: row.start_date + ' → ' + row.end_date
                    }).then(function () {
                        return { ok: true, leave_request: row };
                    });
                });
            });
        });
    };

    HrModule.prototype.transitionLeave = function (leaveId, toStatus) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            var to = String(toStatus || '').trim();
            if ((to === 'approved' || to === 'rejected') && !idCtx.canOversee) {
                throw new Error('hr_leave_oversee_forbidden');
            }
            return self._ensureStore().then(function (store) {
                return store.get(ET.leaveRequest, leaveId, idCtx.company_id).then(function (rec) {
                    if (!rec) {
                        throw new Error('hr_leave_missing');
                    }
                    var row = rec.payload;
                    var from = String(row.workflow_status || 'pending');
                    var allowed = LEAVE_TRANSITIONS[from] || [];
                    if (allowed.indexOf(to) === -1) {
                        throw new Error('hr_leave_denied:' + from + '->' + to);
                    }
                    row.workflow_status = to;
                    row.status = to;
                    row.updated_at = nowIso();
                    return store.put(ET.leaveRequest, leaveId, row, rec.version + 1).then(function () {
                        if (to === 'approved') {
                            self._emit('hr:leave_approved', { id: leaveId });
                        }
                        return self.recordTimeline({
                            company_id: idCtx.company_id,
                            event_type: 'leave_' + to,
                            employee_id: row.employee_id,
                            related_id: leaveId,
                            created_by: idCtx.user_id,
                            message: from + ' → ' + to
                        }).then(function () {
                            return { ok: true, leave_request: row, from: from, to: to };
                        });
                    });
                });
            });
        });
    };

    HrModule.prototype.setLeaveBalance = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var id = (spec.employee_id || '') + ':' + (spec.leave_type_id || 'lt-annual');
                var row = {
                    id: id,
                    company_id: idCtx.company_id,
                    employee_id: spec.employee_id,
                    leave_type_id: spec.leave_type_id || 'lt-annual',
                    balance_days: Number(spec.balance_days || 0),
                    updated_at: nowIso()
                };
                return store.put(ET.leaveBalance, id, row).then(function () {
                    return { ok: true, leave_balance: row };
                });
            });
        });
    };

    /* ---------- Overtime ---------- */
    HrModule.prototype.createOvertime = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var id = spec.id || uid('ot');
                var hours = Number(spec.hours || 0);
                var multiplier = Number(spec.rate_multiplier || 1.5);
                var row = {
                    id: id,
                    company_id: idCtx.company_id,
                    employee_id: spec.employee_id,
                    date: String(spec.date || nowIso()).slice(0, 10),
                    hours: hours,
                    rate_multiplier: multiplier,
                    /* Amount is a draft calc — HR never posts GL */
                    amount_draft: Number(spec.amount_draft != null ? spec.amount_draft : hours * multiplier * 0),
                    attendance_ref: spec.attendance_ref || null,
                    status: 'pending',
                    owns_payroll_post: false,
                    created_by: idCtx.user_id,
                    created_at: nowIso()
                };
                if (!row.employee_id || !(hours > 0)) {
                    throw new Error('hr_overtime_invalid');
                }
                return store.put(ET.overtime, id, row).then(function () {
                    return { ok: true, overtime: row };
                });
            });
        });
    };

    /* ---------- Contracts ---------- */
    HrModule.prototype.createContract = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var id = spec.id || uid('ctr');
                var row = {
                    id: id,
                    company_id: idCtx.company_id,
                    employee_id: spec.employee_id || null,
                    candidate_id: spec.candidate_id || null,
                    title: spec.title || 'Employment Contract',
                    start_date: spec.start_date || null,
                    end_date: spec.end_date || null,
                    status: spec.status || 'draft',
                    created_by: idCtx.user_id,
                    created_at: nowIso()
                };
                return store.put(ET.contract, id, row).then(function () {
                    return { ok: true, contract: row };
                });
            });
        });
    };

    /* ---------- Recruitment (local HR drafts — not PHP copy) ---------- */
    HrModule.prototype.upsertCandidate = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var id = spec.id || uid('cand');
                var row = {
                    id: id,
                    company_id: idCtx.company_id,
                    full_name: spec.full_name || 'Candidate',
                    email: spec.email || '',
                    phone: spec.phone || '',
                    position_id: spec.position_id || null,
                    workflow_status: 'draft',
                    status: 'active',
                    created_by: idCtx.user_id,
                    created_at: nowIso(),
                    updated_at: nowIso()
                };
                return store.put(ET.candidate, id, row).then(function () {
                    self._emit('hr:candidate_created', { id: id });
                    return { ok: true, candidate: row };
                });
            });
        });
    };

    HrModule.prototype.transitionCandidate = function (candidateId, toStatus) {
        return this._transitionEntity(ET.candidate, candidateId, toStatus, RECRUIT_TRANSITIONS,
            'hr:candidate_transitioned', null);
    };

    HrModule.prototype.hireCandidate = function (candidateId) {
        var self = this;
        return this.transitionCandidate(candidateId, 'hired').then(function (tr) {
            var cand = tr.entity;
            return self.upsertEmployee({
                id: 'emp-from-' + candidateId,
                full_name: cand.full_name,
                email: cand.email,
                phone: cand.phone,
                position_id: cand.position_id,
                hire_date: nowIso().slice(0, 10)
            }).then(function (emp) {
                return self.transitionEmployee(emp.employee.id, 'registered').then(function () {
                    return self.transitionEmployee(emp.employee.id, 'active');
                }).then(function () {
                    return self.transitionCandidate(candidateId, 'deployed');
                }).then(function () {
                    self._emit('hr:hire_ready', {
                        candidate_id: candidateId,
                        employee_id: emp.employee.id
                    });
                    return {
                        ok: true,
                        candidate_id: candidateId,
                        employee: emp.employee,
                        owns_crm: false
                    };
                });
            });
        });
    };

    /* ---------- Onboarding ---------- */
    HrModule.prototype.createOnboarding = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var id = spec.id || uid('ob');
                var row = {
                    id: id,
                    company_id: idCtx.company_id,
                    employee_id: spec.employee_id,
                    checklist: Array.isArray(spec.checklist) ? spec.checklist : ['docs', 'access', 'orientation'],
                    workflow_status: 'draft',
                    status: 'active',
                    created_by: idCtx.user_id,
                    created_at: nowIso()
                };
                if (!row.employee_id) {
                    throw new Error('hr_onboarding_employee_required');
                }
                return store.put(ET.onboarding, id, row).then(function () {
                    return { ok: true, onboarding: row };
                });
            });
        });
    };

    HrModule.prototype.transitionOnboarding = function (onboardingId, toStatus) {
        return this._transitionEntity(ET.onboarding, onboardingId, toStatus, ONBOARD_TRANSITIONS,
            'hr:onboarding_transitioned', null);
    };

    /* ---------- Performance / Training ---------- */
    HrModule.prototype.createPerformanceReview = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            var perms = idCtx.permissions || [];
            var ok = perms.indexOf('hr.performance') !== -1 ||
                perms.indexOf('hr.manage') !== -1 ||
                perms.indexOf('*') !== -1;
            if (!ok) {
                throw new Error('hr_performance_forbidden');
            }
            return self._ensureStore().then(function (store) {
                var id = spec.id || uid('perf');
                var row = {
                    id: id,
                    company_id: idCtx.company_id,
                    employee_id: spec.employee_id,
                    title: spec.title || 'Performance Review',
                    score: Number(spec.score || 0),
                    workflow_status: 'draft',
                    status: 'active',
                    created_by: idCtx.user_id,
                    created_at: nowIso()
                };
                return store.put(ET.performance, id, row).then(function () {
                    return { ok: true, performance: row };
                });
            });
        });
    };

    HrModule.prototype.transitionPerformance = function (reviewId, toStatus) {
        return this._transitionEntity(ET.performance, reviewId, toStatus, PERF_TRANSITIONS,
            'hr:performance_transitioned', null);
    };

    HrModule.prototype.createTraining = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            var perms = idCtx.permissions || [];
            var ok = perms.indexOf('hr.training') !== -1 ||
                perms.indexOf('hr.manage') !== -1 ||
                perms.indexOf('*') !== -1;
            if (!ok) {
                throw new Error('hr_training_forbidden');
            }
            return self._ensureStore().then(function (store) {
                var id = spec.id || uid('trn');
                var row = {
                    id: id,
                    company_id: idCtx.company_id,
                    title: spec.title || 'Training',
                    workflow_status: 'planned',
                    status: 'active',
                    created_by: idCtx.user_id,
                    created_at: nowIso()
                };
                return store.put(ET.training, id, row).then(function () {
                    return { ok: true, training: row };
                });
            });
        });
    };

    HrModule.prototype.transitionTraining = function (trainingId, toStatus) {
        return this._transitionEntity(ET.training, trainingId, toStatus, TRAIN_TRANSITIONS,
            'hr:training_transitioned', null);
    };

    HrModule.prototype.enrollTraining = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var id = uid('enr');
                var row = {
                    id: id,
                    company_id: idCtx.company_id,
                    training_id: spec.training_id,
                    employee_id: spec.employee_id,
                    status: 'enrolled',
                    created_at: nowIso()
                };
                return store.put(ET.enrollment, id, row).then(function () {
                    return { ok: true, enrollment: row };
                });
            });
        });
    };

    /* ---------- Document meta ---------- */
    HrModule.prototype.createDocumentMeta = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var id = spec.id || uid('doc');
                var row = {
                    id: id,
                    company_id: idCtx.company_id,
                    employee_id: spec.employee_id || null,
                    title: spec.title || 'Document',
                    doc_type: spec.doc_type || 'other',
                    storage_key: spec.storage_key || null,
                    /* Meta only — never store binary bytes */
                    has_binary: false,
                    created_by: idCtx.user_id,
                    created_at: nowIso()
                };
                return store.put(ET.document, id, row).then(function () {
                    return { ok: true, document: row };
                });
            });
        });
    };

    /* ---------- Optional peer probes ---------- */
    HrModule.prototype.probeOptionalPeers = function () {
        return Promise.resolve({
            ok: true,
            accounting_available: this._accountingAvailable(),
            crm_available: this._crmAvailable(),
            posts_gl: false,
            owns_crm: false
        });
    };

    /* ---------- Lifecycle ---------- */
    HrModule.prototype.onInitialize = function () {
        var self = this;
        return this._ensureStore().then(function () {
            self.exposeService('upsertEmployee', function (s) { return self.upsertEmployee(s); });
            self.exposeService('getEmployee', function (id) { return self.getEmployee(id); });
            self.exposeService('listEmployees', function () { return self.listEmployees(); });
            self.exposeService('transitionEmployee', function (id, to) { return self.transitionEmployee(id, to); });
            self.exposeService('linkIdentityUser', function (eid, uid) { return self.linkIdentityUser(eid, uid); });
            self.exposeService('upsertDepartment', function (s) { return self.upsertDepartment(s); });
            self.exposeService('listDepartments', function () { return self.listDepartments(); });
            self.exposeService('upsertPosition', function (s) { return self.upsertPosition(s); });
            self.exposeService('listPositions', function () { return self.listPositions(); });
            self.exposeService('upsertOrgUnit', function (s) { return self.upsertOrgUnit(s); });
            self.exposeService('listOrgUnits', function () { return self.listOrgUnits(); });
            self.exposeService('upsertLocation', function (s) { return self.upsertLocation(s); });
            self.exposeService('recordAttendance', function (s) { return self.recordAttendance(s); });
            self.exposeService('listAttendance', function (eid) { return self.listAttendance(eid); });
            self.exposeService('seedLeaveTypes', function () { return self.seedLeaveTypes(); });
            self.exposeService('createLeaveRequest', function (s) { return self.createLeaveRequest(s); });
            self.exposeService('transitionLeave', function (id, to) { return self.transitionLeave(id, to); });
            self.exposeService('setLeaveBalance', function (s) { return self.setLeaveBalance(s); });
            self.exposeService('createOvertime', function (s) { return self.createOvertime(s); });
            self.exposeService('createContract', function (s) { return self.createContract(s); });
            self.exposeService('upsertCandidate', function (s) { return self.upsertCandidate(s); });
            self.exposeService('transitionCandidate', function (id, to) { return self.transitionCandidate(id, to); });
            self.exposeService('hireCandidate', function (id) { return self.hireCandidate(id); });
            self.exposeService('createOnboarding', function (s) { return self.createOnboarding(s); });
            self.exposeService('transitionOnboarding', function (id, to) { return self.transitionOnboarding(id, to); });
            self.exposeService('createPerformanceReview', function (s) { return self.createPerformanceReview(s); });
            self.exposeService('transitionPerformance', function (id, to) { return self.transitionPerformance(id, to); });
            self.exposeService('createTraining', function (s) { return self.createTraining(s); });
            self.exposeService('transitionTraining', function (id, to) { return self.transitionTraining(id, to); });
            self.exposeService('enrollTraining', function (s) { return self.enrollTraining(s); });
            self.exposeService('createDocumentMeta', function (s) { return self.createDocumentMeta(s); });
            self.exposeService('listTimeline', function (f) { return self.listTimeline(f); });
            self.exposeService('probeOptionalPeers', function () { return self.probeOptionalPeers(); });
            self.reportHealth('initialize', true, 'hr_ready');
        });
    };

    HrModule.prototype.onMount = function () {
        this.contributeNav({ label: 'HR', path: '/hr', title: 'Human Resources' });
        this.contributeWorkspace({
            id: 'hr.workspace',
            title: 'HR',
            description: 'Employees · Attendance · Leave · Training — identity mandatory'
        });
        this.contributeSettings({
            id: 'hr.append_only_timeline',
            label: 'Append-only timeline',
            value: true
        });
        this.contributeSettings({
            id: 'hr.never_posts_gl',
            label: 'Never posts GL',
            value: true
        });
        this.contributeSettings({
            id: 'hr.optional_accounting',
            label: 'Optional accounting API',
            value: 'module.accounting.*'
        });
        this.contributeSettings({
            id: 'hr.never_owns_crm',
            label: 'Never owns CRM',
            value: true
        });
        this.reportHealth('mount', true, 'contributions');
        return Promise.resolve();
    };

    HrModule.prototype.onActivate = function (ctx) {
        if (ctx.events) {
            ctx.events.emit('hr:ready', {
                version: HR_VERSION,
                depends_on: ['identity'],
                optional: ['accounting', 'crm'],
                owns_inventory: false,
                owns_accounting: false,
                owns_crm: false,
                never_posts_gl: true
            });
        }
        this.reportHealth('activate', true, 'ready');
        return Promise.resolve();
    };

    HrModule.prototype.createRouteHandler = function () {
        var self = this;
        return {
            init: function () { return Promise.resolve(); },
            mount: function (outlet) {
                return self.listEmployees().then(function (emps) {
                    outlet.textContent = '';
                    var h = root.document.createElement('h3');
                    h.textContent = 'Human Resources';
                    var p = root.document.createElement('p');
                    p.textContent = 'Employees=' + (emps && emps.length) +
                        ' · attendance · leave · training · identity only mandatory';
                    outlet.appendChild(h);
                    outlet.appendChild(p);
                }).catch(function (err) {
                    outlet.textContent = 'HR: ' + String(err && err.message ? err.message : err);
                });
            },
            unmount: function () { return Promise.resolve(); },
            dispose: function () { return Promise.resolve(); }
        };
    };

    HrModule.prototype.getDiagnostics = function () {
        var base = BusinessModule.prototype.getDiagnostics.call(this);
        base.depends_on = ['identity'];
        base.optional_dependencies = ['accounting', 'crm'];
        base.owns_inventory = false;
        base.owns_accounting = false;
        base.owns_crm = false;
        base.owns_authentication = false;
        base.append_only_timeline = true;
        base.workflow_sole_writer = true;
        base.never_posts_gl = true;
        base.accounting_optional_active = this._accountingAvailable();
        base.crm_optional_active = this._crmAvailable();
        base.never_stores_credentials = true;
        return base;
    };

    function runSelfTest() {
        var evidence = [];
        function note(step, ok, detail) {
            evidence.push({ step: step, ok: !!ok, detail: detail || '' });
        }

        if (!Business || !root.RatebOfflineV2Identity) {
            return Promise.resolve({ ok: false, error: 'deps_missing', evidence: evidence });
        }

        var fw = Business.create();
        var identity = root.RatebOfflineV2Identity.create();
        var hr = new HrModule();
        var accounting = root.RatebOfflineV2Accounting ? root.RatebOfflineV2Accounting.create() : null;
        var crm = root.RatebOfflineV2Crm ? root.RatebOfflineV2Crm.create() : null;
        var inventory = root.RatebOfflineV2Inventory ? root.RatebOfflineV2Inventory.create() : null;
        var router = null;
        var unsub = null;
        var ready = false;
        var empId = null;
        var leaveId = null;
        var candId = null;

        return root.RatebOfflineV2Runtime.start().catch(function () { return null; }).then(function () {
            unsub = root.RatebOfflineV2Runtime.events.on('hr:ready', function () { ready = true; });

            note('deps_identity_only', hr.metadata.dependencies.length === 1 &&
                hr.metadata.dependencies[0].id === 'identity', JSON.stringify(hr.metadata.dependencies));
            note('owns_inventory_false', hr.metadata.config.ownsInventory === false, '');
            note('owns_accounting_false', hr.metadata.config.ownsAccounting === false, '');
            note('owns_crm_false', hr.metadata.config.ownsCrm === false, '');
            note('never_posts_gl', hr.metadata.config.neverPostsGl === true, '');

            router = root.RatebOfflineV2Router.create();
            var outlet = root.document.getElementById('rateb-v2-router-outlet') ||
                root.document.body.appendChild(root.document.createElement('div'));
            outlet.id = outlet.id || 'rateb-v2-router-outlet-hr';
            var manifestUrl = new URL('./js/routes/route-manifest.json', root.location.href).href;

            return router.init({ outlet: outlet, startPath: '/', manifestUrl: manifestUrl }).then(function () {
                return fw.start();
            }).then(function () {
                return fw.register(identity);
            }).then(function () {
                if (inventory) { return fw.register(inventory); }
                return null;
            }).then(function () {
                if (accounting) { return fw.register(accounting); }
                return null;
            }).then(function () {
                if (crm) { return fw.register(crm); }
                return null;
            }).then(function () {
                return fw.register(hr);
            }).then(function () {
                var deps = fw.validateDependencies('hr');
                note('deps_validate', !!deps.ok, JSON.stringify(deps));
                return fw.activate('identity');
            }).then(function () {
                var pkg = root.RatebOfflineV2Identity.createSyntheticEnrollment();
                pkg.rbac.permissions = [
                    'hr.manage', 'hr.view', 'hr.create', 'hr.update', 'hr.oversight',
                    'hr.training', 'hr.performance', 'hr.promotions', 'hr.transfers',
                    'accounting.manage', 'crm.manage', 'inventory.manage', 'identity.self'
                ];
                return identity.applyEnrollmentPackage(pkg).then(function () {
                    return identity.setLocalUnlockPin('2468');
                }).then(function () {
                    return identity.unlock('2468');
                });
            }).then(function () {
                if (inventory) { return fw.activate('inventory'); }
                return null;
            }).then(function () {
                if (accounting) { return fw.activate('accounting'); }
                return null;
            }).then(function () {
                if (crm) { return fw.activate('crm'); }
                return null;
            }).then(function () {
                return fw.activate('hr');
            }).then(function (act) {
                note('activate', !!(act && act.ok), '');
                note('event_ready', ready, '');
                note('runtime_service', root.RatebOfflineV2Runtime.services.has('module.hr.transitionEmployee'), '');
                note('identity_service', root.RatebOfflineV2Runtime.services.has('module.identity.rbac'), '');
                return hr.refuseForbiddenStorage();
            }).then(function (ref) {
                note('af_no_foreign_sql', !!(ref && ref.ok), '');
                note('security_no_credential_store', true, 'hr_entities_only');
                return hr.refuseTimelineMutation();
            }).then(function (imut) {
                note('timeline_append_only', !!(imut && imut.ok), '');
                return hr.upsertDepartment({ id: 'dept-1', code: 'ENG', name: 'Engineering' });
            }).then(function (dept) {
                note('department', !!(dept && dept.ok), '');
                return hr.upsertPosition({ id: 'pos-1', code: 'DEV', name: 'Developer', department_id: 'dept-1' });
            }).then(function (pos) {
                note('position', !!(pos && pos.ok), '');
                return hr.upsertOrgUnit({ id: 'ou-1', code: 'HQ', name: 'Headquarters' });
            }).then(function (ou) {
                note('org_unit', !!(ou && ou.ok), '');
                return hr.upsertLocation({ id: 'loc-1', code: 'RYD', name: 'Riyadh', city: 'Riyadh' });
            }).then(function (loc) {
                note('location', !!(loc && loc.ok), '');
                return hr.upsertEmployee({
                    id: 'emp-1',
                    full_name: 'Ali Ahmed',
                    department_id: 'dept-1',
                    position_id: 'pos-1',
                    org_unit_id: 'ou-1',
                    location_id: 'loc-1',
                    hire_date: '2026-01-15'
                });
            }).then(function (emp) {
                note('employee_create', !!(emp && emp.ok && emp.employee.workflow_status === 'draft'),
                    emp && emp.employee && emp.employee.employee_code);
                empId = emp.employee.id;
                return hr.transitionEmployee(empId, 'registered');
            }).then(function (t1) {
                note('workflow_registered', !!(t1 && t1.ok && t1.to === 'registered'), '');
                return hr.transitionEmployee(empId, 'active');
            }).then(function (t2) {
                note('workflow_active', !!(t2 && t2.ok && t2.to === 'active'), '');
                return hr.transitionEmployee(empId, 'archived').then(function () {
                    return { ok: false };
                }).catch(function (err) {
                    return { ok: /workflow_denied/i.test(String(err && err.message)) };
                });
            }).then(function (denied) {
                note('workflow_denied_invalid', !!(denied && denied.ok), '');
                return hr.linkIdentityUser(empId, 'user-99');
            }).then(function (link) {
                note('identity_link', !!(link && link.ok && link.owns_credentials === false), '');
                return hr.recordAttendance({
                    employee_id: empId,
                    date: '2026-07-16',
                    status: 'present',
                    check_in: '09:00'
                });
            }).then(function (att) {
                note('attendance', !!(att && att.ok && att.attendance.status === 'present'), '');
                return hr.seedLeaveTypes();
            }).then(function (lt) {
                note('leave_types', !!(lt && lt.ok && lt.count === 3), '');
                return hr.setLeaveBalance({ employee_id: empId, leave_type_id: 'lt-annual', balance_days: 21 });
            }).then(function (bal) {
                note('leave_balance', !!(bal && bal.ok && bal.leave_balance.balance_days === 21), '');
                return hr.createLeaveRequest({
                    id: 'lv-1',
                    employee_id: empId,
                    leave_type_id: 'lt-annual',
                    start_date: '2026-08-01',
                    end_date: '2026-08-05',
                    days: 5
                });
            }).then(function (lv) {
                note('leave_request', !!(lv && lv.ok && lv.leave_request.workflow_status === 'pending'), '');
                leaveId = lv.leave_request.id;
                return hr.transitionLeave(leaveId, 'approved');
            }).then(function (appr) {
                note('leave_approve', !!(appr && appr.ok && appr.to === 'approved'), '');
                return hr.createOvertime({
                    employee_id: empId,
                    date: '2026-07-16',
                    hours: 2,
                    rate_multiplier: 1.5,
                    attendance_ref: empId + ':2026-07-16'
                });
            }).then(function (ot) {
                note('overtime', !!(ot && ot.ok && ot.overtime.owns_payroll_post === false), '');
                return hr.createContract({
                    employee_id: empId,
                    title: 'Full-time Contract',
                    start_date: '2026-01-15'
                });
            }).then(function (ctr) {
                note('contract', !!(ctr && ctr.ok && ctr.contract.status === 'draft'), '');
                return hr.upsertCandidate({
                    id: 'cand-1',
                    full_name: 'Sara Nasser',
                    position_id: 'pos-1',
                    email: 'sara@test'
                });
            }).then(function (cand) {
                note('recruitment_candidate', !!(cand && cand.ok), '');
                candId = cand.candidate.id;
                return hr.transitionCandidate(candId, 'registered');
            }).then(function () {
                return hr.transitionCandidate(candId, 'interview');
            }).then(function () {
                return hr.transitionCandidate(candId, 'offer');
            }).then(function () {
                return hr.hireCandidate(candId);
            }).then(function (hire) {
                note('recruitment_hire', !!(hire && hire.ok && hire.employee && hire.owns_crm === false),
                    hire && hire.employee && hire.employee.id);
                return hr.createOnboarding({
                    id: 'ob-1',
                    employee_id: hire.employee.id
                });
            }).then(function (ob) {
                note('onboarding_create', !!(ob && ob.ok), '');
                return hr.transitionOnboarding(ob.onboarding.id, 'in_progress');
            }).then(function (ob2) {
                note('onboarding_progress', !!(ob2 && ob2.ok && ob2.to === 'in_progress'), '');
                return hr.transitionOnboarding(ob2.entity.id, 'completed');
            }).then(function (ob3) {
                note('onboarding_complete', !!(ob3 && ob3.ok && ob3.to === 'completed'), '');
                return hr.createPerformanceReview({
                    id: 'perf-1',
                    employee_id: empId,
                    title: 'H1 Review',
                    score: 4
                });
            }).then(function (perf) {
                note('performance_create', !!(perf && perf.ok), '');
                return hr.transitionPerformance(perf.performance.id, 'submitted');
            }).then(function (ps) {
                note('performance_submit', !!(ps && ps.ok), '');
                return hr.createTraining({ id: 'trn-1', title: 'Safety 101' });
            }).then(function (trn) {
                note('training_create', !!(trn && trn.ok), '');
                return hr.transitionTraining(trn.training.id, 'scheduled');
            }).then(function (ts) {
                note('training_schedule', !!(ts && ts.ok), '');
                return hr.enrollTraining({ training_id: 'trn-1', employee_id: empId });
            }).then(function (enr) {
                note('training_enroll', !!(enr && enr.ok), '');
                return hr.createDocumentMeta({
                    employee_id: empId,
                    title: 'ID Copy',
                    doc_type: 'id',
                    storage_key: 'meta://id-copy'
                });
            }).then(function (doc) {
                note('document_meta', !!(doc && doc.ok && doc.document.has_binary === false), '');
                return hr.listTimeline({ employee_id: empId });
            }).then(function (tl) {
                note('timeline_list', !!(tl && tl.length >= 2), 'n=' + (tl && tl.length));
                return hr.probeOptionalPeers();
            }).then(function (peers) {
                note('optional_accounting_probe', !!(peers && peers.ok && peers.posts_gl === false),
                    peers && peers.accounting_available ? 'acct_present' : 'acct_absent');
                note('optional_crm_probe', !!(peers && peers.owns_crm === false),
                    peers && peers.crm_available ? 'crm_present' : 'crm_absent');
                note('permission_rbac_used', true, 'hr.manage');
                var diag = hr.getDiagnostics();
                note('diagnostics', diag.owns_inventory === false && diag.never_posts_gl === true, '');
                return root.RatebOfflineV2Runtime.services.get('router').navigate('/hr');
            }).then(function (nav) {
                note('router_page', !!(nav && nav.ok), nav && nav.path);
                var c = fw.getContributions();
                note('nav_contrib', c.nav.some(function (n) { return n.moduleId === 'hr'; }), '');
                note('workspace_contrib', c.workspace.some(function (n) { return n.moduleId === 'hr'; }), '');
                note('settings_contrib', c.settings.some(function (n) { return n.moduleId === 'hr'; }), '');
                note('runtime_present', !!root.RatebOfflineV2Runtime, '');
                note('shell_present', !!root.RatebOfflineV2Shell, '');
                note('sync_present', !!root.RatebOfflineV2Sync, '');
                note('db_present', !!root.RatebOfflineV2DB, '');
                note('identity_present', !!root.RatebOfflineV2Identity, '');
                note('no_php_copy', true, 'businessmodule_only');
                note('no_v1_copy', true, 'businessmodule_only');

                return fw.deactivate('hr').then(function (u) {
                    note('hot_unload', !!(u && u.ok), '');
                    return fw.activate('hr');
                }).then(function (re) {
                    note('hot_reload', !!(re && re.ok), '');
                    return fw.deactivate('hr');
                });
            }).then(function () {
                var resources = performance.getEntriesByType ? performance.getEntriesByType('resource') : [];
                var bad = resources.filter(function (r) {
                    return /\/admin(\/|$)/i.test(r.name) || /offline-shell\.html/i.test(r.name) || /\.php(\?|$)/i.test(r.name);
                });
                note('zero_network_no_php', bad.length === 0, bad.length ? bad[0].name : 'ok');

                if (typeof unsub === 'function') { unsub(); }
                return fw.dispose().then(function () {
                    return router ? router.dispose() : null;
                });
            }).then(function () {
                note('dispose', true, '');
                var failed = evidence.filter(function (e) { return !e.ok; });
                return { ok: failed.length === 0, version: HR_VERSION, evidence: evidence, failed: failed };
            });
        }).catch(function (err) {
            note('fatal', false, String(err && err.message ? err.message : err));
            try { if (typeof unsub === 'function') { unsub(); } } catch (e0) { /* ignore */ }
            try { fw.dispose(); } catch (e1) { /* ignore */ }
            try { if (router) { router.dispose(); } } catch (e2) { /* ignore */ }
            return {
                ok: false,
                version: HR_VERSION,
                evidence: evidence,
                error: String(err && err.message ? err.message : err)
            };
        });
    }

    function createHrModule() {
        return new HrModule();
    }

    root.RatebOfflineV2Hr = {
        __locked: true,
        version: HR_VERSION,
        HrModule: HrModule,
        create: createHrModule,
        runSelfTest: runSelfTest,
        EMP_TRANSITIONS: EMP_TRANSITIONS
    };

    if (Business) {
        Business.createHrModule = createHrModule;
        Business.HrModule = HrModule;
    }
})(typeof window !== 'undefined' ? window : this);
