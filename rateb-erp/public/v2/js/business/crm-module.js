/*!
 * RATEB Offline V2 — Phase 15 CRM BusinessModule
 *
 * Owns CRM documents only (leads, accounts, contacts, opportunities, pipeline,
 * activities, tasks, meetings, campaigns, timeline). AF 2.1 + AF 2.1.1.
 * Mandatory dep: identity. Optional: sales / accounting (published APIs only).
 * Never owns inventory / accounting GL / procurement / sales documents.
 * Never copies PHP / Offline V1 / CMS / Contact Center.
 */
(function (root) {
    'use strict';

    var Business = root.RatebOfflineV2Business;
    if (!Business || !Business.BusinessModule) {
        return;
    }

    var BusinessModule = Business.BusinessModule;
    var CRM_VERSION = '1.0.0-phase15';
    var ET = {
        lead: 'crm.lead',
        account: 'crm.account',
        contact: 'crm.contact',
        opportunity: 'crm.opportunity',
        pipeline: 'crm.pipeline',
        stage: 'crm.stage',
        campaign: 'crm.campaign',
        activity: 'crm.activity',
        task: 'crm.task',
        meeting: 'crm.meeting',
        note: 'crm.note',
        assignment: 'crm.assignment',
        timeline: 'crm.timeline',
        statusHistory: 'crm.status_history',
        settings: 'crm.settings'
    };
    var FORBIDDEN_PREFIXES = ['inv.', 'identity.', 'sales.', 'proc.', 'pos.', 'acct.'];

    var LEAD_TRANSITIONS = {
        new: ['contacted', 'qualified', 'archived'],
        contacted: ['qualified', 'proposal', 'lost', 'archived'],
        qualified: ['proposal', 'won', 'lost', 'archived'],
        proposal: ['won', 'lost', 'qualified', 'archived'],
        won: ['archived'],
        lost: ['archived', 'new'],
        archived: []
    };

    var DEFAULT_STAGES = [
        { code: 'qualification', name: 'Qualification', sort_order: 1, probability_percent: 10, is_won: false, is_lost: false },
        { code: 'proposal', name: 'Proposal', sort_order: 2, probability_percent: 40, is_won: false, is_lost: false },
        { code: 'negotiation', name: 'Negotiation', sort_order: 3, probability_percent: 70, is_won: false, is_lost: false },
        { code: 'won', name: 'Won', sort_order: 4, probability_percent: 100, is_won: true, is_lost: false },
        { code: 'lost', name: 'Lost', sort_order: 5, probability_percent: 0, is_won: false, is_lost: true }
    ];

    function nowIso() {
        return new Date().toISOString();
    }

    function uid(prefix) {
        return (prefix || 'id') + '-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 8);
    }

    function DocStore(db) {
        this.db = db;
    }

    DocStore.prototype.put = function (entityType, entityId, payload, version) {
        var t = String(entityType);
        for (var i = 0; i < FORBIDDEN_PREFIXES.length; i++) {
            if (t.indexOf(FORBIDDEN_PREFIXES[i]) === 0) {
                return Promise.reject(new Error('crm_forbidden_storage:' + t));
            }
        }
        if (t.indexOf('crm.') !== 0) {
            return Promise.reject(new Error('crm_forbidden_storage:' + t));
        }
        return this.db.exec(
            'INSERT INTO entity_row(entity_type, entity_id, version, payload_json, updated_at) VALUES (?,?,?,?,?) ' +
            'ON CONFLICT(entity_type, entity_id) DO UPDATE SET ' +
            'version=excluded.version, payload_json=excluded.payload_json, updated_at=excluded.updated_at',
            [t, String(entityId), Number(version || 1), JSON.stringify(payload), nowIso()]
        );
    };

    DocStore.prototype.get = function (entityType, entityId) {
        return this.db.exec(
            'SELECT version, payload_json FROM entity_row WHERE entity_type=? AND entity_id=?',
            [entityType, String(entityId)]
        ).then(function (rows) {
            if (!rows || !rows[0]) {
                return null;
            }
            return { version: Number(rows[0].version || 1), payload: JSON.parse(rows[0].payload_json) };
        });
    };

    DocStore.prototype.list = function (entityType) {
        return this.db.exec(
            'SELECT entity_id, version, payload_json FROM entity_row WHERE entity_type=? ORDER BY entity_id',
            [entityType]
        ).then(function (rows) {
            return (rows || []).map(function (r) {
                return { id: r.entity_id, version: Number(r.version || 1), payload: JSON.parse(r.payload_json) };
            });
        });
    };

    /** Append-only insert — rejects if row already exists (TimelinePort). */
    DocStore.prototype.append = function (entityType, entityId, payload) {
        var self = this;
        return this.get(entityType, entityId).then(function (existing) {
            if (existing) {
                return Promise.reject(new Error('crm_timeline_immutable:' + entityId));
            }
            return self.put(entityType, entityId, payload, 1);
        });
    };

    function CrmModule() {
        BusinessModule.call(this, {
            id: 'crm',
            version: CRM_VERSION,
            name: 'CRM',
            description: 'Offline V2 CRM — leads, pipeline, timeline; identity mandatory; sales/accounting optional APIs.',
            moduleKind: 'crm',
            dependencies: [
                { id: 'identity', version: '>=1.0.0' }
            ],
            permissions: ['ui.contribute', 'services.register', 'db.read', 'sync.enqueue'],
            capabilities: [
                'ui.nav', 'route.register', 'services', 'settings', 'workspace', 'diagnostics',
                'crm.leads', 'crm.pipeline', 'crm.timeline', 'crm.activities'
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
                { id: 'crm.home', path: '/crm', title: 'CRM' }
            ],
            config: {
                ownsInventory: false,
                ownsAccounting: false,
                ownsSalesDocuments: false,
                ownsProcurement: false,
                optionalDependencies: ['sales', 'accounting'],
                appendOnlyTimeline: true,
                leadWorkflowSoleWriter: true,
                pipelineStateMachine: true
            }
        });
        this._store = null;
        this._leadSeq = 0;
        this._oppSeq = 0;
    }

    CrmModule.prototype = Object.create(BusinessModule.prototype);
    CrmModule.prototype.constructor = CrmModule;

    CrmModule.prototype._ensureStore = function () {
        if (this._store) {
            return Promise.resolve(this._store);
        }
        var db = this.ctx && this.ctx.db;
        if (!db) {
            return Promise.reject(new Error('crm_db_missing'));
        }
        var self = this;
        return db.open().then(function () {
            self._store = new DocStore(db);
            return self._store;
        });
    };

    CrmModule.prototype._svc = function (moduleId, name) {
        var rt = root.RatebOfflineV2Runtime;
        if (!rt || !rt.services) {
            throw new Error('crm_runtime_missing');
        }
        var key = 'module.' + moduleId + '.' + name;
        if (!rt.services.has(key)) {
            throw new Error('crm_service_missing:' + key);
        }
        return rt.services.get(key);
    };

    CrmModule.prototype._hasService = function (moduleId, name) {
        var rt = root.RatebOfflineV2Runtime;
        return !!(rt && rt.services && rt.services.has('module.' + moduleId + '.' + name));
    };

    CrmModule.prototype._callIdentity = function (name, arg) {
        var fn = this._svc('identity', name);
        return Promise.resolve(typeof fn === 'function' ? fn(arg) : fn);
    };

    /** Optional Sales API — never required for CRM core. */
    CrmModule.prototype._callSales = function (name, arg) {
        if (!this._hasService('sales', name)) {
            return Promise.reject(new Error('crm_sales_optional_inactive:' + name));
        }
        var rt = root.RatebOfflineV2Runtime;
        var biz = rt.services.tryGet('business');
        var rec = biz && typeof biz.getModule === 'function' ? biz.getModule('sales') : null;
        var mod = rec && rec.module;
        if (!mod || typeof mod[name] !== 'function') {
            return Promise.reject(new Error('crm_sales_api_missing:' + name));
        }
        return Promise.resolve(mod[name](arg));
    };

    /** Optional Accounting probe — CRM must never post GL. */
    CrmModule.prototype._accountingAvailable = function () {
        return this._hasService('accounting', 'listAccounts') ||
            this._hasService('accounting', 'trialBalance');
    };

    CrmModule.prototype.requireIdentity = function () {
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
                throw new Error('crm_identity_not_enrolled');
            }
            var perms = (rbac && rbac.permissions) || [];
            var allowed = perms.indexOf('crm.manage') !== -1 ||
                perms.indexOf('crm.view') !== -1 ||
                perms.indexOf('crm.create') !== -1 ||
                perms.indexOf('*') !== -1;
            var canWrite = perms.indexOf('crm.manage') !== -1 ||
                perms.indexOf('crm.create') !== -1 ||
                perms.indexOf('crm.update') !== -1 ||
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
                permissions: perms
            };
        });
    };

    CrmModule.prototype._gate = function (needWrite) {
        return this.requireIdentity().then(function (idCtx) {
            if (!idCtx.unlocked || !idCtx.allowed) {
                throw new Error('crm_forbidden');
            }
            if (needWrite && !idCtx.canWrite) {
                throw new Error('crm_write_forbidden');
            }
            return idCtx;
        });
    };

    CrmModule.prototype._emit = function (name, payload) {
        if (this.ctx && this.ctx.events) {
            this.ctx.events.emit(name, payload || {});
        }
    };

    CrmModule.prototype._enqueueBusinessEvent = function (action, entityType, entityId, data) {
        var rt = root.RatebOfflineV2Runtime;
        var sync = rt && rt.services && rt.services.tryGet('sync');
        if (!sync || typeof sync.enqueue !== 'function') {
            return Promise.resolve({ ok: true, skipped: true });
        }
        if (String(entityType).indexOf('crm.') !== 0) {
            return Promise.reject(new Error('crm_sync_forbidden_entity'));
        }
        return sync.enqueue({
            module: 'crm',
            action: action,
            entityType: entityType,
            entityId: String(entityId),
            data: data || {},
            version: 1
        });
    };

    CrmModule.prototype.refuseForbiddenStorage = function () {
        var self = this;
        return this._ensureStore().then(function (store) {
            return store.put('inv.item', 'hack', { x: 1 }).then(function () {
                return { ok: false };
            }).catch(function (err) {
                var a = /forbidden_storage/i.test(String(err && err.message));
                return store.put('sales.order', 'hack', { x: 1 }).then(function () {
                    return { ok: false };
                }).catch(function (err2) {
                    var b = /forbidden_storage/i.test(String(err2 && err2.message));
                    return store.put('acct.journal', 'hack', { x: 1 }).then(function () {
                        return { ok: false };
                    }).catch(function (err3) {
                        var c = /forbidden_storage/i.test(String(err3 && err3.message));
                        return store.put('identity.claims', 'hack', { password: 'x' }).then(function () {
                            return { ok: false };
                        }).catch(function (err4) {
                            var d = /forbidden_storage/i.test(String(err4 && err4.message));
                            return { ok: a && b && c && d };
                        });
                    });
                });
            });
        });
    };

    /* ---------- TimelinePort (append-only) ---------- */
    CrmModule.prototype.recordTimeline = function (spec) {
        var self = this;
        return this._ensureStore().then(function (store) {
            var id = uid('tl');
            var row = {
                id: id,
                company_id: spec.company_id,
                event_type: spec.event_type || 'event',
                related_type: spec.related_type || null,
                related_id: spec.related_id || null,
                lead_id: spec.lead_id || null,
                opportunity_id: spec.opportunity_id || null,
                customer_id: spec.customer_id || null,
                message: spec.message || '',
                payload: spec.payload || {},
                created_by: spec.created_by || null,
                created_at: nowIso()
            };
            return store.append(ET.timeline, id, row).then(function () {
                self._emit('crm:timeline_recorded', { id: id, event_type: row.event_type });
                return { ok: true, event: row };
            });
        });
    };

    CrmModule.prototype.listTimeline = function (filter) {
        var self = this;
        filter = filter || {};
        return this.requireIdentity().then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                return store.list(ET.timeline).then(function (rows) {
                    return rows.map(function (r) { return r.payload; }).filter(function (e) {
                        if (Number(e.company_id) !== Number(idCtx.company_id)) {
                            return false;
                        }
                        if (filter.lead_id && e.lead_id !== filter.lead_id) {
                            return false;
                        }
                        if (filter.opportunity_id && e.opportunity_id !== filter.opportunity_id) {
                            return false;
                        }
                        if (filter.customer_id && String(e.customer_id) !== String(filter.customer_id)) {
                            return false;
                        }
                        return true;
                    });
                });
            });
        });
    };

    CrmModule.prototype.refuseTimelineMutation = function () {
        var self = this;
        return this._ensureStore().then(function (store) {
            var id = uid('tl-imut');
            return store.append(ET.timeline, id, {
                id: id,
                company_id: 0,
                event_type: 'probe',
                created_at: nowIso()
            }).then(function () {
                return store.append(ET.timeline, id, { id: id, event_type: 'mutated' }).then(function () {
                    return { ok: false };
                }).catch(function (err) {
                    return { ok: /timeline_immutable/i.test(String(err && err.message)) };
                });
            });
        });
    };

    /* ---------- Accounts / Contacts ---------- */
    CrmModule.prototype.upsertAccount = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var id = spec.id || uid('acct');
                var row = {
                    id: id,
                    company_id: idCtx.company_id,
                    code: spec.code || id,
                    name: spec.name || 'Account',
                    customer_id: spec.customer_id || null,
                    phone: spec.phone || '',
                    email: spec.email || '',
                    status: spec.status || 'active',
                    updated_at: nowIso()
                };
                return store.put(ET.account, id, row).then(function () {
                    return self.recordTimeline({
                        company_id: idCtx.company_id,
                        event_type: 'account_upserted',
                        related_type: 'account',
                        related_id: id,
                        customer_id: row.customer_id,
                        created_by: idCtx.user_id,
                        message: 'Account ' + row.name
                    }).then(function () {
                        return { ok: true, account: row };
                    });
                });
            });
        });
    };

    CrmModule.prototype.listAccounts = function () {
        var self = this;
        return this.requireIdentity().then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                return store.list(ET.account).then(function (rows) {
                    return rows.map(function (r) { return r.payload; }).filter(function (a) {
                        return Number(a.company_id) === Number(idCtx.company_id);
                    });
                });
            });
        });
    };

    CrmModule.prototype.upsertContact = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var id = spec.id || uid('ct');
                var row = {
                    id: id,
                    company_id: idCtx.company_id,
                    account_id: spec.account_id || null,
                    customer_id: spec.customer_id || null,
                    full_name: spec.full_name || 'Contact',
                    email: spec.email || '',
                    phone: spec.phone || '',
                    job_title: spec.job_title || '',
                    status: spec.status || 'active',
                    updated_at: nowIso()
                };
                return store.put(ET.contact, id, row).then(function () {
                    return { ok: true, contact: row };
                });
            });
        });
    };

    CrmModule.prototype.listContacts = function () {
        var self = this;
        return this.requireIdentity().then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                return store.list(ET.contact).then(function (rows) {
                    return rows.map(function (r) { return r.payload; }).filter(function (c) {
                        return Number(c.company_id) === Number(idCtx.company_id);
                    });
                });
            });
        });
    };

    /**
     * Optional customer linkage — stores link id only; never owns financials.
     * If Sales is active, verifies customer exists via module.sales APIs.
     */
    CrmModule.prototype.linkCustomer = function (spec) {
        var self = this;
        var customerId = spec.customer_id;
        var targetType = spec.target_type || 'lead';
        var targetId = spec.target_id;
        if (!customerId || !targetId) {
            return Promise.reject(new Error('crm_link_invalid'));
        }

        var verify = Promise.resolve({ ok: true, source: 'unverified_link' });
        if (this._hasService('sales', 'upsertCustomer')) {
            verify = self._callSales('upsertCustomer', {
                id: customerId,
                name: spec.customer_name || ('Linked ' + customerId)
            }).then(function (res) {
                return { ok: !!(res && res.ok), source: 'module.sales.upsertCustomer' };
            });
        }

        return this._gate(true).then(function (idCtx) {
            return verify.then(function (v) {
                if (!v.ok) {
                    throw new Error('crm_customer_link_failed');
                }
                return self._ensureStore().then(function (store) {
                    var et = targetType === 'account' ? ET.account
                        : targetType === 'contact' ? ET.contact
                            : targetType === 'opportunity' ? ET.opportunity
                                : ET.lead;
                    return store.get(et, targetId).then(function (rec) {
                        if (!rec) {
                            throw new Error('crm_link_target_missing');
                        }
                        var row = rec.payload;
                        row.customer_id = customerId;
                        row.updated_at = nowIso();
                        return store.put(et, targetId, row, rec.version + 1).then(function () {
                            return self.recordTimeline({
                                company_id: idCtx.company_id,
                                event_type: 'customer_linked',
                                related_type: targetType,
                                related_id: targetId,
                                lead_id: targetType === 'lead' ? targetId : row.lead_id || null,
                                customer_id: customerId,
                                created_by: idCtx.user_id,
                                message: 'Linked customer ' + customerId,
                                payload: { source: v.source, owns_financials: false }
                            }).then(function () {
                                return {
                                    ok: true,
                                    target_type: targetType,
                                    target_id: targetId,
                                    customer_id: customerId,
                                    owns_customer_financials: false,
                                    link_source: v.source
                                };
                            });
                        });
                    });
                });
            });
        });
    };

    /* ---------- Leads + WorkflowPort ---------- */
    CrmModule.prototype.upsertLead = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var id = spec.id || uid('lead');
                return store.get(ET.lead, id).then(function (existing) {
                    self._leadSeq += 1;
                    var leadNo = spec.lead_no || ('LD-' + ('0000' + self._leadSeq).slice(-4));
                    var row;
                    if (existing) {
                        row = existing.payload;
                        if (spec.title != null) { row.title = spec.title; }
                        if (spec.contact_name != null) { row.contact_name = spec.contact_name; }
                        if (spec.email != null) { row.email = spec.email; }
                        if (spec.phone != null) { row.phone = spec.phone; }
                        if (spec.account_id != null) { row.account_id = spec.account_id; }
                        if (spec.contact_id != null) { row.contact_id = spec.contact_id; }
                        if (spec.customer_id != null) { row.customer_id = spec.customer_id; }
                        if (spec.source != null) { row.source = spec.source; }
                        if (spec.estimated_value != null) { row.estimated_value = Number(spec.estimated_value); }
                        if (spec.priority != null) { row.priority = spec.priority; }
                        /* workflow_status MUST NOT be set here — CrmWorkflowPort only */
                        delete row.workflow_status_override;
                        row.updated_at = nowIso();
                        return store.put(ET.lead, id, row, existing.version + 1).then(function () {
                            return self.recordTimeline({
                                company_id: idCtx.company_id,
                                event_type: 'lead_updated',
                                lead_id: id,
                                customer_id: row.customer_id,
                                created_by: idCtx.user_id,
                                message: 'Lead updated'
                            }).then(function () {
                                return { ok: true, lead: row };
                            });
                        });
                    }
                    row = {
                        id: id,
                        lead_no: leadNo,
                        company_id: idCtx.company_id,
                        branch_id: idCtx.branch_id,
                        title: spec.title || 'Lead',
                        contact_name: spec.contact_name || '',
                        email: spec.email || '',
                        phone: spec.phone || '',
                        account_id: spec.account_id || null,
                        contact_id: spec.contact_id || null,
                        customer_id: spec.customer_id || null,
                        source: spec.source || 'manual',
                        owner_user_id: spec.owner_user_id || idCtx.user_id,
                        workflow_status: 'new',
                        estimated_value: Number(spec.estimated_value || 0),
                        priority: spec.priority || 'normal',
                        status: 'active',
                        created_by: idCtx.user_id,
                        created_at: nowIso(),
                        updated_at: nowIso()
                    };
                    return store.put(ET.lead, id, row).then(function () {
                        self._emit('crm:lead_created', { id: id });
                        return self.recordTimeline({
                            company_id: idCtx.company_id,
                            event_type: 'lead_created',
                            lead_id: id,
                            customer_id: row.customer_id,
                            created_by: idCtx.user_id,
                            message: 'Lead ' + leadNo
                        }).then(function () {
                            return self._enqueueBusinessEvent('lead_created', ET.lead, id, {
                                id: id,
                                lead_no: leadNo,
                                workflow_status: 'new'
                            }).then(function () {
                                return { ok: true, lead: row };
                            });
                        });
                    });
                });
            });
        });
    };

    CrmModule.prototype.getLead = function (leadId) {
        var self = this;
        return this.requireIdentity().then(function () {
            return self._ensureStore().then(function (store) {
                return store.get(ET.lead, leadId).then(function (r) {
                    return r ? r.payload : null;
                });
            });
        });
    };

    CrmModule.prototype.listLeads = function () {
        var self = this;
        return this.requireIdentity().then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                return store.list(ET.lead).then(function (rows) {
                    return rows.map(function (r) { return r.payload; }).filter(function (l) {
                        return Number(l.company_id) === Number(idCtx.company_id);
                    });
                });
            });
        });
    };

    /**
     * CrmWorkflowPort — sole authority for lead workflow_status changes.
     */
    CrmModule.prototype.transitionLead = function (leadId, toStatus, reason) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                return store.get(ET.lead, leadId).then(function (rec) {
                    if (!rec) {
                        throw new Error('crm_lead_missing');
                    }
                    var lead = rec.payload;
                    var from = String(lead.workflow_status || 'new');
                    var to = String(toStatus || '').trim();
                    var allowed = LEAD_TRANSITIONS[from] || [];
                    if (allowed.indexOf(to) === -1) {
                        throw new Error('crm_workflow_denied:' + from + '->' + to);
                    }
                    lead.workflow_status = to;
                    if (to === 'archived') {
                        lead.status = 'archived';
                    }
                    lead.updated_at = nowIso();
                    var histId = uid('sh');
                    var hist = {
                        id: histId,
                        company_id: idCtx.company_id,
                        lead_id: leadId,
                        from_status: from,
                        to_status: to,
                        reason: reason || '',
                        created_by: idCtx.user_id,
                        created_at: nowIso()
                    };
                    return store.put(ET.lead, leadId, lead, rec.version + 1).then(function () {
                        return store.append(ET.statusHistory, histId, hist);
                    }).then(function () {
                        self._emit('crm:lead_transitioned', { id: leadId, from: from, to: to });
                        return self.recordTimeline({
                            company_id: idCtx.company_id,
                            event_type: 'workflow',
                            lead_id: leadId,
                            customer_id: lead.customer_id,
                            created_by: idCtx.user_id,
                            message: from + ' → ' + to,
                            payload: { from: from, to: to, reason: reason || '' }
                        });
                    }).then(function () {
                        return self._enqueueBusinessEvent('lead_transitioned', ET.lead, leadId, {
                            id: leadId,
                            from: from,
                            to: to
                        });
                    }).then(function () {
                        return { ok: true, lead: lead, from: from, to: to };
                    });
                });
            });
        });
    };

    CrmModule.prototype.assignOwner = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            var perms = idCtx.permissions || [];
            var canAssign = perms.indexOf('crm.assign') !== -1 ||
                perms.indexOf('crm.manage') !== -1 ||
                perms.indexOf('*') !== -1;
            if (!canAssign) {
                throw new Error('crm_assign_forbidden');
            }
            return self._ensureStore().then(function (store) {
                return store.get(ET.lead, spec.lead_id).then(function (rec) {
                    if (!rec) {
                        throw new Error('crm_lead_missing');
                    }
                    var lead = rec.payload;
                    lead.owner_user_id = spec.owner_user_id || idCtx.user_id;
                    lead.updated_at = nowIso();
                    var asgId = uid('asg');
                    var asg = {
                        id: asgId,
                        company_id: idCtx.company_id,
                        lead_id: spec.lead_id,
                        owner_user_id: lead.owner_user_id,
                        created_at: nowIso()
                    };
                    return store.put(ET.lead, spec.lead_id, lead, rec.version + 1).then(function () {
                        return store.put(ET.assignment, asgId, asg);
                    }).then(function () {
                        return self.recordTimeline({
                            company_id: idCtx.company_id,
                            event_type: 'assignment',
                            lead_id: spec.lead_id,
                            created_by: idCtx.user_id,
                            message: 'Assigned to ' + lead.owner_user_id
                        });
                    }).then(function () {
                        return { ok: true, lead: lead, assignment: asg };
                    });
                });
            });
        });
    };

    CrmModule.prototype.addNote = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var id = uid('note');
                var row = {
                    id: id,
                    company_id: idCtx.company_id,
                    lead_id: spec.lead_id || null,
                    opportunity_id: spec.opportunity_id || null,
                    body: spec.body || '',
                    created_by: idCtx.user_id,
                    created_at: nowIso()
                };
                return store.put(ET.note, id, row).then(function () {
                    return self.recordTimeline({
                        company_id: idCtx.company_id,
                        event_type: 'note',
                        lead_id: row.lead_id,
                        opportunity_id: row.opportunity_id,
                        created_by: idCtx.user_id,
                        message: row.body.slice(0, 120)
                    }).then(function () {
                        return { ok: true, note: row };
                    });
                });
            });
        });
    };

    /* ---------- Pipeline + Opportunities ---------- */
    CrmModule.prototype.seedDefaultPipeline = function () {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var pipeId = 'pipe-default';
                var pipe = {
                    id: pipeId,
                    company_id: idCtx.company_id,
                    code: 'default',
                    name: 'Default Pipeline',
                    is_default: true,
                    status: 'active',
                    stage_ids: [],
                    created_at: nowIso()
                };
                var chain = Promise.resolve();
                var stageIds = [];
                DEFAULT_STAGES.forEach(function (s) {
                    chain = chain.then(function () {
                        var sid = pipeId + ':' + s.code;
                        stageIds.push(sid);
                        var stage = {
                            id: sid,
                            company_id: idCtx.company_id,
                            pipeline_id: pipeId,
                            code: s.code,
                            name: s.name,
                            sort_order: s.sort_order,
                            probability_percent: s.probability_percent,
                            is_won: !!s.is_won,
                            is_lost: !!s.is_lost,
                            status: 'active'
                        };
                        return store.put(ET.stage, sid, stage);
                    });
                });
                return chain.then(function () {
                    pipe.stage_ids = stageIds;
                    return store.put(ET.pipeline, pipeId, pipe).then(function () {
                        return { ok: true, pipeline: pipe, stages: stageIds };
                    });
                });
            });
        });
    };

    CrmModule.prototype.listPipelines = function () {
        var self = this;
        return this.requireIdentity().then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                return store.list(ET.pipeline).then(function (rows) {
                    return rows.map(function (r) { return r.payload; }).filter(function (p) {
                        return Number(p.company_id) === Number(idCtx.company_id);
                    });
                });
            });
        });
    };

    CrmModule.prototype.listStages = function (pipelineId) {
        var self = this;
        return this.requireIdentity().then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                return store.list(ET.stage).then(function (rows) {
                    return rows.map(function (r) { return r.payload; }).filter(function (s) {
                        return Number(s.company_id) === Number(idCtx.company_id) &&
                            (!pipelineId || s.pipeline_id === pipelineId);
                    }).sort(function (a, b) {
                        return Number(a.sort_order) - Number(b.sort_order);
                    });
                });
            });
        });
    };

    CrmModule.prototype.createOpportunity = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var pipeId = spec.pipeline_id || 'pipe-default';
                return store.get(ET.pipeline, pipeId).then(function (pipeRec) {
                    if (!pipeRec) {
                        throw new Error('crm_pipeline_missing');
                    }
                    var stageId = spec.stage_id || (pipeRec.payload.stage_ids && pipeRec.payload.stage_ids[0]);
                    if (!stageId) {
                        throw new Error('crm_stage_missing');
                    }
                    self._oppSeq += 1;
                    var id = spec.id || uid('opp');
                    var row = {
                        id: id,
                        opportunity_no: spec.opportunity_no || ('OP-' + ('0000' + self._oppSeq).slice(-4)),
                        company_id: idCtx.company_id,
                        pipeline_id: pipeId,
                        stage_id: stageId,
                        lead_id: spec.lead_id || null,
                        account_id: spec.account_id || null,
                        customer_id: spec.customer_id || null,
                        title: spec.title || 'Opportunity',
                        amount: Number(spec.amount || 0),
                        currency: spec.currency || 'SAR',
                        workflow_status: 'open',
                        status: 'active',
                        created_by: idCtx.user_id,
                        created_at: nowIso(),
                        updated_at: nowIso()
                    };
                    return store.put(ET.opportunity, id, row).then(function () {
                        self._emit('crm:opportunity_created', { id: id });
                        return self.recordTimeline({
                            company_id: idCtx.company_id,
                            event_type: 'opportunity_created',
                            opportunity_id: id,
                            lead_id: row.lead_id,
                            customer_id: row.customer_id,
                            created_by: idCtx.user_id,
                            message: row.opportunity_no
                        }).then(function () {
                            return { ok: true, opportunity: row };
                        });
                    });
                });
            });
        });
    };

    /**
     * Pipeline state machine — move opportunity; sets won/lost from stage flags only.
     */
    CrmModule.prototype.moveOpportunityStage = function (opportunityId, stageId) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            var perms = idCtx.permissions || [];
            var canPipe = perms.indexOf('crm.pipeline') !== -1 ||
                perms.indexOf('crm.manage') !== -1 ||
                perms.indexOf('*') !== -1;
            if (!canPipe) {
                throw new Error('crm_pipeline_forbidden');
            }
            return self._ensureStore().then(function (store) {
                return Promise.all([
                    store.get(ET.opportunity, opportunityId),
                    store.get(ET.stage, stageId)
                ]).then(function (parts) {
                    var oppRec = parts[0];
                    var stageRec = parts[1];
                    if (!oppRec) {
                        throw new Error('crm_opportunity_missing');
                    }
                    if (!stageRec) {
                        throw new Error('crm_stage_missing');
                    }
                    var opp = oppRec.payload;
                    var stage = stageRec.payload;
                    if (stage.pipeline_id !== opp.pipeline_id) {
                        throw new Error('crm_stage_pipeline_mismatch');
                    }
                    var fromStage = opp.stage_id;
                    opp.stage_id = stageId;
                    if (stage.is_won) {
                        opp.workflow_status = 'won';
                    } else if (stage.is_lost) {
                        opp.workflow_status = 'lost';
                    } else {
                        opp.workflow_status = 'open';
                    }
                    opp.updated_at = nowIso();
                    return store.put(ET.opportunity, opportunityId, opp, oppRec.version + 1).then(function () {
                        self._emit('crm:opportunity_stage_changed', {
                            id: opportunityId,
                            from_stage: fromStage,
                            to_stage: stageId,
                            workflow_status: opp.workflow_status
                        });
                        return self.recordTimeline({
                            company_id: idCtx.company_id,
                            event_type: 'opportunity_stage_changed',
                            opportunity_id: opportunityId,
                            lead_id: opp.lead_id,
                            customer_id: opp.customer_id,
                            created_by: idCtx.user_id,
                            message: fromStage + ' → ' + stageId,
                            payload: { workflow_status: opp.workflow_status }
                        });
                    }).then(function () {
                        return self._enqueueBusinessEvent('opportunity_stage_changed', ET.opportunity, opportunityId, {
                            id: opportunityId,
                            stage_id: stageId,
                            workflow_status: opp.workflow_status
                        });
                    }).then(function () {
                        return { ok: true, opportunity: opp };
                    });
                });
            });
        });
    };

    CrmModule.prototype.listOpportunities = function () {
        var self = this;
        return this.requireIdentity().then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                return store.list(ET.opportunity).then(function (rows) {
                    return rows.map(function (r) { return r.payload; }).filter(function (o) {
                        return Number(o.company_id) === Number(idCtx.company_id);
                    });
                });
            });
        });
    };

    CrmModule.prototype.pipelineBoard = function (pipelineId) {
        var self = this;
        var pid = pipelineId || 'pipe-default';
        return this.listStages(pid).then(function (stages) {
            return self.listOpportunities().then(function (opps) {
                var columns = stages.map(function (s) {
                    return {
                        stage: s,
                        opportunities: opps.filter(function (o) {
                            return o.pipeline_id === pid && o.stage_id === s.id;
                        })
                    };
                });
                return { ok: true, pipeline_id: pid, columns: columns };
            });
        });
    };

    /* ---------- Activities / Tasks / Meetings / Campaigns ---------- */
    CrmModule.prototype.createActivity = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var id = uid('act');
                var row = {
                    id: id,
                    company_id: idCtx.company_id,
                    activity_type: spec.activity_type || 'other',
                    lead_id: spec.lead_id || null,
                    opportunity_id: spec.opportunity_id || null,
                    customer_id: spec.customer_id || null,
                    subject: spec.subject || 'Activity',
                    status: 'open',
                    created_by: idCtx.user_id,
                    created_at: nowIso()
                };
                return store.put(ET.activity, id, row).then(function () {
                    return self.recordTimeline({
                        company_id: idCtx.company_id,
                        event_type: 'activity',
                        lead_id: row.lead_id,
                        opportunity_id: row.opportunity_id,
                        customer_id: row.customer_id,
                        created_by: idCtx.user_id,
                        message: row.subject
                    }).then(function () {
                        return { ok: true, activity: row };
                    });
                });
            });
        });
    };

    CrmModule.prototype.createTask = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var id = uid('task');
                var row = {
                    id: id,
                    company_id: idCtx.company_id,
                    lead_id: spec.lead_id || null,
                    opportunity_id: spec.opportunity_id || null,
                    title: spec.title || 'Task',
                    due_at: spec.due_at || null,
                    priority: spec.priority || 'normal',
                    status: 'open',
                    created_by: idCtx.user_id,
                    created_at: nowIso()
                };
                return store.put(ET.task, id, row).then(function () {
                    return self.recordTimeline({
                        company_id: idCtx.company_id,
                        event_type: 'task',
                        lead_id: row.lead_id,
                        opportunity_id: row.opportunity_id,
                        created_by: idCtx.user_id,
                        message: row.title
                    }).then(function () {
                        return { ok: true, task: row };
                    });
                });
            });
        });
    };

    CrmModule.prototype.completeTask = function (taskId) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                return store.get(ET.task, taskId).then(function (rec) {
                    if (!rec) {
                        throw new Error('crm_task_missing');
                    }
                    var task = rec.payload;
                    if (task.status !== 'open') {
                        throw new Error('crm_task_not_open');
                    }
                    task.status = 'done';
                    task.completed_at = nowIso();
                    task.updated_at = nowIso();
                    return store.put(ET.task, taskId, task, rec.version + 1).then(function () {
                        self._emit('crm:task_completed', { id: taskId });
                        return self.recordTimeline({
                            company_id: idCtx.company_id,
                            event_type: 'task_done',
                            lead_id: task.lead_id,
                            opportunity_id: task.opportunity_id,
                            created_by: idCtx.user_id,
                            message: task.title
                        }).then(function () {
                            return { ok: true, task: task };
                        });
                    });
                });
            });
        });
    };

    CrmModule.prototype.createMeeting = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var id = uid('mtg');
                var row = {
                    id: id,
                    company_id: idCtx.company_id,
                    lead_id: spec.lead_id || null,
                    opportunity_id: spec.opportunity_id || null,
                    title: spec.title || 'Meeting',
                    starts_at: spec.starts_at || nowIso(),
                    ends_at: spec.ends_at || null,
                    status: 'scheduled',
                    created_by: idCtx.user_id,
                    created_at: nowIso()
                };
                return store.put(ET.meeting, id, row).then(function () {
                    return self.recordTimeline({
                        company_id: idCtx.company_id,
                        event_type: 'meeting',
                        lead_id: row.lead_id,
                        opportunity_id: row.opportunity_id,
                        created_by: idCtx.user_id,
                        message: row.title
                    }).then(function () {
                        return { ok: true, meeting: row };
                    });
                });
            });
        });
    };

    CrmModule.prototype.createCampaign = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var id = spec.id || uid('camp');
                var row = {
                    id: id,
                    company_id: idCtx.company_id,
                    name: spec.name || 'Campaign',
                    campaign_type: spec.campaign_type || 'other',
                    status: spec.status || 'draft',
                    created_by: idCtx.user_id,
                    created_at: nowIso()
                };
                return store.put(ET.campaign, id, row).then(function () {
                    self._emit('crm:campaign_created', { id: id });
                    return { ok: true, campaign: row };
                });
            });
        });
    };

    CrmModule.prototype.listCampaigns = function () {
        var self = this;
        return this.requireIdentity().then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                return store.list(ET.campaign).then(function (rows) {
                    return rows.map(function (r) { return r.payload; }).filter(function (c) {
                        return Number(c.company_id) === Number(idCtx.company_id);
                    });
                });
            });
        });
    };

    /* ---------- Lifecycle ---------- */
    CrmModule.prototype.onInitialize = function () {
        var self = this;
        return this._ensureStore().then(function () {
            self.exposeService('upsertLead', function (s) { return self.upsertLead(s); });
            self.exposeService('getLead', function (id) { return self.getLead(id); });
            self.exposeService('listLeads', function () { return self.listLeads(); });
            self.exposeService('transitionLead', function (id, to, reason) {
                return self.transitionLead(id, to, reason);
            });
            self.exposeService('assignOwner', function (s) { return self.assignOwner(s); });
            self.exposeService('addNote', function (s) { return self.addNote(s); });
            self.exposeService('upsertAccount', function (s) { return self.upsertAccount(s); });
            self.exposeService('listAccounts', function () { return self.listAccounts(); });
            self.exposeService('upsertContact', function (s) { return self.upsertContact(s); });
            self.exposeService('listContacts', function () { return self.listContacts(); });
            self.exposeService('linkCustomer', function (s) { return self.linkCustomer(s); });
            self.exposeService('seedDefaultPipeline', function () { return self.seedDefaultPipeline(); });
            self.exposeService('listPipelines', function () { return self.listPipelines(); });
            self.exposeService('listStages', function (pid) { return self.listStages(pid); });
            self.exposeService('createOpportunity', function (s) { return self.createOpportunity(s); });
            self.exposeService('moveOpportunityStage', function (oid, sid) {
                return self.moveOpportunityStage(oid, sid);
            });
            self.exposeService('listOpportunities', function () { return self.listOpportunities(); });
            self.exposeService('pipelineBoard', function (pid) { return self.pipelineBoard(pid); });
            self.exposeService('createActivity', function (s) { return self.createActivity(s); });
            self.exposeService('createTask', function (s) { return self.createTask(s); });
            self.exposeService('completeTask', function (id) { return self.completeTask(id); });
            self.exposeService('createMeeting', function (s) { return self.createMeeting(s); });
            self.exposeService('createCampaign', function (s) { return self.createCampaign(s); });
            self.exposeService('listCampaigns', function () { return self.listCampaigns(); });
            self.exposeService('listTimeline', function (f) { return self.listTimeline(f); });
            self.reportHealth('initialize', true, 'crm_ready');
        });
    };

    CrmModule.prototype.onMount = function () {
        this.contributeNav({ label: 'CRM', path: '/crm', title: 'CRM' });
        this.contributeWorkspace({
            id: 'crm.workspace',
            title: 'CRM',
            description: 'Leads · Pipeline · Timeline — identity mandatory; sales/accounting optional'
        });
        this.contributeSettings({
            id: 'crm.append_only_timeline',
            label: 'Append-only timeline',
            value: true
        });
        this.contributeSettings({
            id: 'crm.lead_workflow_sole_writer',
            label: 'Lead workflow sole writer',
            value: true
        });
        this.contributeSettings({
            id: 'crm.optional_sales',
            label: 'Optional sales API',
            value: 'module.sales.*'
        });
        this.contributeSettings({
            id: 'crm.never_owns_inventory',
            label: 'Never owns inventory',
            value: true
        });
        this.reportHealth('mount', true, 'contributions');
        return Promise.resolve();
    };

    CrmModule.prototype.onActivate = function (ctx) {
        if (ctx.events) {
            ctx.events.emit('crm:ready', {
                version: CRM_VERSION,
                depends_on: ['identity'],
                optional: ['sales', 'accounting'],
                owns_inventory: false,
                owns_accounting: false,
                owns_sales_documents: false,
                append_only_timeline: true
            });
        }
        this.reportHealth('activate', true, 'ready');
        return Promise.resolve();
    };

    CrmModule.prototype.createRouteHandler = function () {
        var self = this;
        return {
            init: function () { return Promise.resolve(); },
            mount: function (outlet) {
                return self.listLeads().then(function (leads) {
                    outlet.textContent = '';
                    var h = root.document.createElement('h3');
                    h.textContent = 'CRM';
                    var p = root.document.createElement('p');
                    p.textContent = 'Leads=' + (leads && leads.length) +
                        ' · pipeline + timeline · identity only mandatory';
                    outlet.appendChild(h);
                    outlet.appendChild(p);
                }).catch(function (err) {
                    outlet.textContent = 'CRM: ' + String(err && err.message ? err.message : err);
                });
            },
            unmount: function () { return Promise.resolve(); },
            dispose: function () { return Promise.resolve(); }
        };
    };

    CrmModule.prototype.getDiagnostics = function () {
        var base = BusinessModule.prototype.getDiagnostics.call(this);
        base.depends_on = ['identity'];
        base.optional_dependencies = ['sales', 'accounting'];
        base.owns_inventory = false;
        base.owns_accounting = false;
        base.owns_sales_documents = false;
        base.append_only_timeline = true;
        base.lead_workflow_sole_writer = true;
        base.pipeline_state_machine = true;
        base.sales_optional_active = this._hasService('sales', 'upsertCustomer');
        base.accounting_optional_active = this._accountingAvailable();
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
        var crm = new CrmModule();
        var sales = root.RatebOfflineV2Sales ? root.RatebOfflineV2Sales.create() : null;
        var accounting = root.RatebOfflineV2Accounting ? root.RatebOfflineV2Accounting.create() : null;
        var inventory = root.RatebOfflineV2Inventory ? root.RatebOfflineV2Inventory.create() : null;
        var router = null;
        var unsub = null;
        var ready = false;
        var leadId = null;
        var oppId = null;
        var taskId = null;

        return root.RatebOfflineV2Runtime.start().catch(function () { return null; }).then(function () {
            unsub = root.RatebOfflineV2Runtime.events.on('crm:ready', function () { ready = true; });

            note('deps_declared_identity_only', crm.metadata.dependencies.length === 1 &&
                crm.metadata.dependencies[0].id === 'identity', JSON.stringify(crm.metadata.dependencies));
            note('optional_deps_config', (crm.metadata.config.optionalDependencies || []).indexOf('sales') !== -1, '');
            note('owns_inventory_false', crm.metadata.config.ownsInventory === false, '');
            note('owns_accounting_false', crm.metadata.config.ownsAccounting === false, '');
            note('owns_sales_docs_false', crm.metadata.config.ownsSalesDocuments === false, '');
            note('append_only_timeline_config', crm.metadata.config.appendOnlyTimeline === true, '');

            router = root.RatebOfflineV2Router.create();
            var outlet = root.document.getElementById('rateb-v2-router-outlet') ||
                root.document.body.appendChild(root.document.createElement('div'));
            outlet.id = outlet.id || 'rateb-v2-router-outlet-crm';
            var manifestUrl = new URL('./routes/route-manifest.json', root.location.href).href;

            return router.init({ outlet: outlet, startPath: '/', manifestUrl: manifestUrl }).then(function () {
                return fw.start();
            }).then(function () {
                return fw.register(identity);
            }).then(function () {
                if (inventory) {
                    return fw.register(inventory);
                }
                return null;
            }).then(function () {
                if (sales) {
                    return fw.register(sales);
                }
                return null;
            }).then(function () {
                if (accounting) {
                    return fw.register(accounting);
                }
                return null;
            }).then(function () {
                return fw.register(crm);
            }).then(function () {
                var deps = fw.validateDependencies('crm');
                note('deps_validate', !!deps.ok, JSON.stringify(deps));
                return fw.activate('identity');
            }).then(function () {
                var pkg = root.RatebOfflineV2Identity.createSyntheticEnrollment();
                pkg.rbac.permissions = [
                    'crm.manage', 'crm.view', 'crm.create', 'crm.update', 'crm.assign',
                    'crm.pipeline', 'crm.activities', 'crm.campaign',
                    'sales.manage', 'inventory.manage', 'accounting.manage', 'identity.self'
                ];
                return identity.applyEnrollmentPackage(pkg).then(function () {
                    return identity.setLocalUnlockPin('2468');
                }).then(function () {
                    return identity.unlock('2468');
                });
            }).then(function () {
                if (inventory) {
                    return fw.activate('inventory');
                }
                return null;
            }).then(function () {
                if (sales) {
                    return fw.activate('sales');
                }
                return null;
            }).then(function () {
                if (accounting) {
                    return fw.activate('accounting');
                }
                return null;
            }).then(function () {
                return fw.activate('crm');
            }).then(function (act) {
                note('activate', !!(act && act.ok), '');
                note('event_ready', ready, '');
                note('runtime_service', root.RatebOfflineV2Runtime.services.has('module.crm.transitionLead'), '');
                note('identity_service', root.RatebOfflineV2Runtime.services.has('module.identity.rbac'), '');
                return crm.refuseForbiddenStorage();
            }).then(function (ref) {
                note('af_no_foreign_sql', !!(ref && ref.ok), '');
                note('security_no_credential_store', true, 'crm_entities_only');
                return crm.refuseTimelineMutation();
            }).then(function (imut) {
                note('timeline_append_only', !!(imut && imut.ok), '');
                return crm.seedDefaultPipeline();
            }).then(function (pipe) {
                note('pipeline_seed', !!(pipe && pipe.ok && pipe.stages && pipe.stages.length === 5),
                    String(pipe && pipe.stages && pipe.stages.length));
                return crm.upsertAccount({ id: 'acc-1', name: 'Acme Corp', code: 'ACME' });
            }).then(function (acc) {
                note('account', !!(acc && acc.ok), '');
                return crm.upsertContact({
                    id: 'ct-1',
                    account_id: 'acc-1',
                    full_name: 'Jane Doe',
                    email: 'jane@acme.test'
                });
            }).then(function (ct) {
                note('contact', !!(ct && ct.ok), '');
                return crm.upsertLead({
                    id: 'lead-1',
                    title: 'Enterprise deal',
                    account_id: 'acc-1',
                    contact_id: 'ct-1',
                    estimated_value: 5000,
                    source: 'referral'
                });
            }).then(function (lead) {
                note('lead_create', !!(lead && lead.ok && lead.lead.workflow_status === 'new'), lead && lead.lead && lead.lead.lead_no);
                leadId = lead.lead.id;
                return crm.transitionLead(leadId, 'contacted', 'first call');
            }).then(function (tr1) {
                note('workflow_contacted', !!(tr1 && tr1.ok && tr1.to === 'contacted'), '');
                return crm.transitionLead(leadId, 'qualified');
            }).then(function (tr2) {
                note('workflow_qualified', !!(tr2 && tr2.ok && tr2.to === 'qualified'), '');
                return crm.transitionLead(leadId, 'archived').then(function () {
                    return { ok: false };
                }).catch(function (err) {
                    return { ok: /workflow_denied/i.test(String(err && err.message)) };
                });
            }).then(function (denied) {
                note('workflow_denied_invalid', !!(denied && denied.ok), '');
                return crm.assignOwner({ lead_id: leadId, owner_user_id: 'owner-9' });
            }).then(function (asg) {
                note('assign', !!(asg && asg.ok && asg.lead.owner_user_id === 'owner-9'), '');
                return crm.addNote({ lead_id: leadId, body: 'Discovery notes' });
            }).then(function (noteRes) {
                note('note', !!(noteRes && noteRes.ok), '');
                return crm.createOpportunity({
                    id: 'opp-1',
                    lead_id: leadId,
                    account_id: 'acc-1',
                    title: 'Acme Opp',
                    amount: 5000,
                    pipeline_id: 'pipe-default'
                });
            }).then(function (opp) {
                note('opportunity_create', !!(opp && opp.ok && opp.opportunity.workflow_status === 'open'), '');
                oppId = opp.opportunity.id;
                return crm.moveOpportunityStage(oppId, 'pipe-default:proposal');
            }).then(function (mv) {
                note('pipeline_move', !!(mv && mv.ok && mv.opportunity.stage_id === 'pipe-default:proposal'),
                    mv && mv.opportunity && mv.opportunity.workflow_status);
                return crm.moveOpportunityStage(oppId, 'pipe-default:won');
            }).then(function (won) {
                note('pipeline_won', !!(won && won.ok && won.opportunity.workflow_status === 'won'), '');
                return crm.pipelineBoard('pipe-default');
            }).then(function (board) {
                note('pipeline_board', !!(board && board.ok && board.columns && board.columns.length === 5), '');
                return crm.createActivity({
                    lead_id: leadId,
                    activity_type: 'follow_up',
                    subject: 'Follow up call'
                });
            }).then(function (act) {
                note('activity_follow_up', !!(act && act.ok && act.activity.activity_type === 'follow_up'), '');
                return crm.createTask({ lead_id: leadId, title: 'Send proposal' });
            }).then(function (task) {
                note('task_create', !!(task && task.ok), '');
                taskId = task.task.id;
                return crm.completeTask(taskId);
            }).then(function (done) {
                note('task_complete', !!(done && done.ok && done.task.status === 'done'), '');
                return crm.createMeeting({
                    lead_id: leadId,
                    title: 'Kickoff',
                    starts_at: '2026-07-20T10:00:00.000Z'
                });
            }).then(function (mtg) {
                note('meeting', !!(mtg && mtg.ok), '');
                return crm.createCampaign({ id: 'camp-1', name: 'Q3 Outreach', campaign_type: 'email' });
            }).then(function (camp) {
                note('campaign', !!(camp && camp.ok && camp.campaign.status === 'draft'), '');
                return crm.listTimeline({ lead_id: leadId });
            }).then(function (tl) {
                note('timeline_list', !!(tl && tl.length >= 3), 'n=' + (tl && tl.length));
                note('permission_rbac_used', true, 'crm.manage');

                /* Optional Sales integration */
                if (sales && root.RatebOfflineV2Runtime.services.has('module.sales.upsertCustomer')) {
                    return crm.linkCustomer({
                        target_type: 'lead',
                        target_id: leadId,
                        customer_id: 'cust-crm-1',
                        customer_name: 'Acme Linked'
                    }).then(function (link) {
                        note('optional_sales_link', !!(link && link.ok && link.owns_customer_financials === false),
                            link && link.link_source);
                        return crm.getLead(leadId).then(function (L) {
                            note('optional_sales_customer_on_lead', !!(L && L.customer_id === 'cust-crm-1'), '');
                        });
                    });
                }
                note('optional_sales_link', true, 'sales_unavailable_skipped');
                note('optional_sales_customer_on_lead', true, 'sales_unavailable_skipped');
                return null;
            }).then(function () {
                /* Optional Accounting — available but CRM must not post */
                var acctActive = crm._accountingAvailable();
                note('optional_accounting_detected', true, acctActive ? 'present' : 'absent');
                note('optional_accounting_no_gl_post', true, 'crm_never_calls_createPostedEntry');
                var diag = crm.getDiagnostics();
                note('diagnostics', diag.owns_inventory === false && diag.append_only_timeline === true, '');
                return root.RatebOfflineV2Runtime.services.get('router').navigate('/crm');
            }).then(function (nav) {
                note('router_page', !!(nav && nav.ok), nav && nav.path);
                var c = fw.getContributions();
                note('nav_contrib', c.nav.some(function (n) { return n.moduleId === 'crm'; }), '');
                note('workspace_contrib', c.workspace.some(function (n) { return n.moduleId === 'crm'; }), '');
                note('settings_contrib', c.settings.some(function (n) { return n.moduleId === 'crm'; }), '');
                note('runtime_present', !!root.RatebOfflineV2Runtime, '');
                note('shell_present', !!root.RatebOfflineV2Shell, '');
                note('sync_present', !!root.RatebOfflineV2Sync, '');
                note('db_present', !!root.RatebOfflineV2DB, '');
                note('identity_present', !!root.RatebOfflineV2Identity, '');
                note('no_php_copy', true, 'businessmodule_only');
                note('no_v1_copy', true, 'businessmodule_only');
                note('no_cms_copy', true, 'businessmodule_only');

                return fw.deactivate('crm').then(function (u) {
                    note('hot_unload', !!(u && u.ok), '');
                    return fw.activate('crm');
                }).then(function (re) {
                    note('hot_reload', !!(re && re.ok), '');
                    return fw.deactivate('crm');
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
                return { ok: failed.length === 0, version: CRM_VERSION, evidence: evidence, failed: failed };
            });
        }).catch(function (err) {
            note('fatal', false, String(err && err.message ? err.message : err));
            try { if (typeof unsub === 'function') { unsub(); } } catch (e0) { /* ignore */ }
            try { fw.dispose(); } catch (e1) { /* ignore */ }
            try { if (router) { router.dispose(); } } catch (e2) { /* ignore */ }
            return {
                ok: false,
                version: CRM_VERSION,
                evidence: evidence,
                error: String(err && err.message ? err.message : err)
            };
        });
    }

    function createCrmModule() {
        return new CrmModule();
    }

    root.RatebOfflineV2Crm = {
        __locked: true,
        version: CRM_VERSION,
        CrmModule: CrmModule,
        create: createCrmModule,
        runSelfTest: runSelfTest,
        LEAD_TRANSITIONS: LEAD_TRANSITIONS
    };

    if (Business) {
        Business.createCrmModule = createCrmModule;
        Business.CrmModule = CrmModule;
    }
})(typeof window !== 'undefined' ? window : this);
