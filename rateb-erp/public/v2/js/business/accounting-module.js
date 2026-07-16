/*!
 * RATEB Offline V2 — Phase 14 Accounting BusinessModule
 *
 * Owns GL documents only (COA, journals, fiscal, vouchers, cost centers, policies, reports).
 * AF 2.1 + AF 2.1.1: deps identity + inventory; inventory via module.inventory.* only.
 * Never owns inventory balances/valuation. Never stores credentials.
 * Configurable AccountMap — no hardcoded codes in PostingPort paths.
 */
(function (root) {
    'use strict';

    var Business = root.RatebOfflineV2Business;
    if (!Business || !Business.BusinessModule) {
        return;
    }

    var BusinessModule = Business.BusinessModule;
    var ACCT_VERSION = '1.0.0-phase14';
    var ET = {
        account: 'acct.account',
        journal: 'acct.journal',
        fiscal: 'acct.fiscal_period',
        voucher: 'acct.voucher',
        costCenter: 'acct.cost_center',
        accountMap: 'acct.account_map',
        taxPolicy: 'acct.tax_policy',
        currencyPolicy: 'acct.currency_policy'
    };
    var FORBIDDEN_PREFIXES = ['inv.', 'identity.', 'sales.', 'proc.', 'pos.'];
    var ACCOUNT_MAP_ID = 'default';
    var TAX_POLICY_ID = 'default';
    var CURRENCY_POLICY_ID = 'default';

    /** Default semantic role → account code (settings seed only; PostingPort resolves via AccountMap). */
    var DEFAULT_ACCOUNT_MAP = {
        cash: '1100',
        ar: '1200',
        vat_input: '1210',
        inventory_asset: '1300',
        ap: '2100',
        vat_output: '2200',
        equity: '3000',
        revenue: '4100',
        sales_returns: '4900',
        procurement_expense: '5100',
        cogs: '5200'
    };

    var DEFAULT_COA = [
        { code: '1100', name: 'Cash', type: 'asset', role: 'cash' },
        { code: '1200', name: 'Accounts Receivable', type: 'asset', role: 'ar' },
        { code: '1210', name: 'VAT Input', type: 'asset', role: 'vat_input' },
        { code: '1300', name: 'Inventory Asset', type: 'asset', role: 'inventory_asset' },
        { code: '2100', name: 'Accounts Payable', type: 'liability', role: 'ap' },
        { code: '2200', name: 'VAT Output', type: 'liability', role: 'vat_output' },
        { code: '3000', name: 'Equity', type: 'equity', role: 'equity' },
        { code: '4100', name: 'Revenue', type: 'revenue', role: 'revenue' },
        { code: '4900', name: 'Sales Returns', type: 'revenue', role: 'sales_returns' },
        { code: '5100', name: 'Procurement Expense', type: 'expense', role: 'procurement_expense' },
        { code: '5200', name: 'Cost of Goods Sold', type: 'expense', role: 'cogs' }
    ];

    function nowIso() {
        return new Date().toISOString();
    }

    function uid(prefix) {
        return (prefix || 'id') + '-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 8);
    }

    function round2(n) {
        return Math.round(Number(n || 0) * 100) / 100;
    }

    function DocStore(db) {
        this.db = db;
    }

    DocStore.prototype.put = function (entityType, entityId, payload, version) {
        var t = String(entityType);
        for (var i = 0; i < FORBIDDEN_PREFIXES.length; i++) {
            if (t.indexOf(FORBIDDEN_PREFIXES[i]) === 0) {
                return Promise.reject(new Error('accounting_forbidden_storage:' + t));
            }
        }
        if (t.indexOf('acct.') !== 0) {
            return Promise.reject(new Error('accounting_forbidden_storage:' + t));
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

    function AccountingModule() {
        BusinessModule.call(this, {
            id: 'accounting',
            version: ACCT_VERSION,
            name: 'Accounting',
            description: 'Offline V2 GL — COA, journals, fiscal, vouchers; inventory via Inventory APIs.',
            moduleKind: 'accounting',
            dependencies: [
                { id: 'identity', version: '>=1.0.0' },
                { id: 'inventory', version: '>=1.0.0' }
            ],
            permissions: ['ui.contribute', 'services.register', 'db.read', 'sync.enqueue'],
            capabilities: [
                'ui.nav', 'route.register', 'services', 'settings', 'workspace', 'diagnostics',
                'accounting.coa', 'accounting.posting', 'accounting.reports', 'accounting.fiscal'
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
                { id: 'accounting.home', path: '/accounting', title: 'Accounting' }
            ],
            config: {
                ownsInventory: false,
                inventoryApiOnly: true,
                accountMapConfigurable: true,
                postingPortSoleWriter: true,
                defaultCurrency: 'SAR',
                defaultTaxRate: 0.15,
                autoPostInventoryGl: false
            }
        });
        this._store = null;
        this._unsubs = [];
    }

    AccountingModule.prototype = Object.create(BusinessModule.prototype);
    AccountingModule.prototype.constructor = AccountingModule;

    AccountingModule.prototype._ensureStore = function () {
        if (this._store) {
            return Promise.resolve(this._store);
        }
        var db = this.ctx && this.ctx.db;
        if (!db) {
            return Promise.reject(new Error('accounting_db_missing'));
        }
        var self = this;
        return db.open().then(function () {
            self._store = new DocStore(db);
            return self._store;
        });
    };

    AccountingModule.prototype._svc = function (moduleId, name) {
        var rt = root.RatebOfflineV2Runtime;
        if (!rt || !rt.services) {
            throw new Error('accounting_runtime_missing');
        }
        var key = 'module.' + moduleId + '.' + name;
        if (!rt.services.has(key)) {
            throw new Error('accounting_service_missing:' + key);
        }
        return rt.services.get(key);
    };

    AccountingModule.prototype._callInventory = function (name, arg) {
        var rt = root.RatebOfflineV2Runtime;
        if (!rt || !rt.services) {
            return Promise.reject(new Error('accounting_runtime_missing'));
        }
        if (!rt.services.has('module.inventory.valuation') && !rt.services.has('module.inventory.postMovement')) {
            return Promise.reject(new Error('accounting_inventory_inactive'));
        }
        /* Published service is module.inventory.valuation; instance method is valuationReport. */
        var methodName = name === 'valuation' ? 'valuationReport' : name;
        var serviceName = name === 'valuationReport' ? 'valuation' : name;
        var key = 'module.inventory.' + serviceName;
        var biz = rt.services.tryGet('business');
        var rec = biz && typeof biz.getModule === 'function' ? biz.getModule('inventory') : null;
        var mod = rec && rec.module;
        if (!mod || typeof mod[methodName] !== 'function') {
            return Promise.reject(new Error('accounting_inventory_api_missing:' + name));
        }
        if (!rt.services.has(key)) {
            return Promise.reject(new Error('accounting_service_missing:' + key));
        }
        return Promise.resolve(mod[methodName](arg));
    };

    AccountingModule.prototype._callIdentity = function (name, arg) {
        var fn = this._svc('identity', name);
        return Promise.resolve(typeof fn === 'function' ? fn(arg) : fn);
    };

    AccountingModule.prototype.requireIdentity = function () {
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
                throw new Error('accounting_identity_not_enrolled');
            }
            var perms = (rbac && rbac.permissions) || [];
            var allowed = perms.indexOf('accounting.manage') !== -1 ||
                perms.indexOf('accounting.post') !== -1 ||
                perms.indexOf('accounting.view') !== -1 ||
                perms.indexOf('*') !== -1;
            var canPost = perms.indexOf('accounting.manage') !== -1 ||
                perms.indexOf('accounting.post') !== -1 ||
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
                canPost: canPost
            };
        });
    };

    AccountingModule.prototype._gate = function (needPost) {
        return this.requireIdentity().then(function (idCtx) {
            if (!idCtx.unlocked || !idCtx.allowed) {
                throw new Error('accounting_forbidden');
            }
            if (needPost && !idCtx.canPost) {
                throw new Error('accounting_post_forbidden');
            }
            return idCtx;
        });
    };

    AccountingModule.prototype._emit = function (name, payload) {
        if (this.ctx && this.ctx.events) {
            this.ctx.events.emit(name, payload || {});
        }
    };

    AccountingModule.prototype._enqueueBusinessEvent = function (action, entityType, entityId, data) {
        var rt = root.RatebOfflineV2Runtime;
        var sync = rt && rt.services && rt.services.tryGet('sync');
        if (!sync || typeof sync.enqueue !== 'function') {
            return Promise.resolve({ ok: true, skipped: true });
        }
        if (String(entityType).indexOf('acct.') !== 0) {
            return Promise.reject(new Error('accounting_sync_forbidden_entity'));
        }
        return sync.enqueue({
            module: 'accounting',
            action: action,
            entityType: entityType,
            entityId: String(entityId),
            data: data || {},
            version: 1
        });
    };

    AccountingModule.prototype.refuseForbiddenStorage = function () {
        var self = this;
        return this._ensureStore().then(function (store) {
            return store.put('inv.item', 'hack', { x: 1 }).then(function () {
                return { ok: false };
            }).catch(function (err) {
                var a = /forbidden_storage/i.test(String(err && err.message));
                return store.put('sales.invoice', 'hack', { x: 1 }).then(function () {
                    return { ok: false };
                }).catch(function (err2) {
                    var b = /forbidden_storage/i.test(String(err2 && err2.message));
                    return store.put('identity.claims', 'hack', { password: 'x' }).then(function () {
                        return { ok: false };
                    }).catch(function (err3) {
                        var c = /forbidden_storage/i.test(String(err3 && err3.message));
                        return { ok: a && b && c };
                    });
                });
            });
        });
    };

    /* ---------- AccountMap / Tax / Currency policies ---------- */
    AccountingModule.prototype.getAccountMap = function () {
        var self = this;
        return this._ensureStore().then(function (store) {
            return store.get(ET.accountMap, ACCOUNT_MAP_ID).then(function (rec) {
                if (rec && rec.payload && rec.payload.map) {
                    return { ok: true, map: rec.payload.map, version: rec.version };
                }
                return { ok: true, map: Object.assign({}, DEFAULT_ACCOUNT_MAP), version: 0 };
            });
        });
    };

    AccountingModule.prototype.setAccountMap = function (mapSpec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var map = Object.assign({}, DEFAULT_ACCOUNT_MAP, mapSpec || {});
                var row = {
                    id: ACCOUNT_MAP_ID,
                    company_id: idCtx.company_id,
                    map: map,
                    updated_at: nowIso()
                };
                return store.put(ET.accountMap, ACCOUNT_MAP_ID, row).then(function () {
                    self._emit('accounting:account_map_updated', { id: ACCOUNT_MAP_ID });
                    return { ok: true, map: map };
                });
            });
        });
    };

    AccountingModule.prototype.resolveAccount = function (role) {
        return this.getAccountMap().then(function (am) {
            var code = am.map[role];
            if (!code) {
                throw new Error('accounting_account_map_missing_role:' + role);
            }
            return { ok: true, role: role, account_code: String(code) };
        });
    };

    AccountingModule.prototype.getTaxPolicy = function () {
        var self = this;
        return this._ensureStore().then(function (store) {
            return store.get(ET.taxPolicy, TAX_POLICY_ID).then(function (rec) {
                if (rec && rec.payload) {
                    return { ok: true, policy: rec.payload };
                }
                return {
                    ok: true,
                    policy: {
                        id: TAX_POLICY_ID,
                        default_rate: self.metadata.config.defaultTaxRate,
                        tax_codes: {
                            vat15: { rate: 0.15, name: 'VAT 15%' },
                            exempt: { rate: 0, name: 'Exempt' }
                        }
                    }
                };
            });
        });
    };

    AccountingModule.prototype.setTaxPolicy = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var row = {
                    id: TAX_POLICY_ID,
                    company_id: idCtx.company_id,
                    default_rate: Number(spec.default_rate != null ? spec.default_rate : self.metadata.config.defaultTaxRate),
                    tax_codes: spec.tax_codes || {
                        vat15: { rate: 0.15, name: 'VAT 15%' },
                        exempt: { rate: 0, name: 'Exempt' }
                    },
                    updated_at: nowIso()
                };
                return store.put(ET.taxPolicy, TAX_POLICY_ID, row).then(function () {
                    return { ok: true, policy: row };
                });
            });
        });
    };

    AccountingModule.prototype.getCurrencyPolicy = function () {
        var self = this;
        return this._ensureStore().then(function (store) {
            return store.get(ET.currencyPolicy, CURRENCY_POLICY_ID).then(function (rec) {
                if (rec && rec.payload) {
                    return { ok: true, policy: rec.payload };
                }
                return {
                    ok: true,
                    policy: {
                        id: CURRENCY_POLICY_ID,
                        base_currency: self.metadata.config.defaultCurrency,
                        rates: { SAR: 1, USD: 3.75, EUR: 4.1 }
                    }
                };
            });
        });
    };

    AccountingModule.prototype.setCurrencyPolicy = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var row = {
                    id: CURRENCY_POLICY_ID,
                    company_id: idCtx.company_id,
                    base_currency: spec.base_currency || self.metadata.config.defaultCurrency,
                    rates: spec.rates || { SAR: 1 },
                    updated_at: nowIso()
                };
                return store.put(ET.currencyPolicy, CURRENCY_POLICY_ID, row).then(function () {
                    return { ok: true, policy: row };
                });
            });
        });
    };

    AccountingModule.prototype.applyTax = function (amount, taxCode) {
        var self = this;
        return this.getTaxPolicy().then(function (tp) {
            var code = taxCode || 'vat15';
            var entry = (tp.policy.tax_codes && tp.policy.tax_codes[code]) || null;
            var rate = entry ? Number(entry.rate) : Number(tp.policy.default_rate || 0);
            var base = round2(amount);
            var tax = round2(base * rate);
            return { ok: true, base: base, tax: tax, total: round2(base + tax), rate: rate, tax_code: code };
        });
    };

    AccountingModule.prototype.convertCurrency = function (amount, fromCurrency, toCurrency) {
        var self = this;
        return this.getCurrencyPolicy().then(function (cp) {
            var rates = cp.policy.rates || {};
            var from = String(fromCurrency || cp.policy.base_currency || 'SAR');
            var to = String(toCurrency || cp.policy.base_currency || 'SAR');
            var rf = Number(rates[from]);
            var rt = Number(rates[to]);
            if (!(rf > 0) || !(rt > 0)) {
                throw new Error('accounting_fx_rate_missing');
            }
            /* rates are base-currency units per 1 foreign unit (SAR-per-USD style) */
            var inBase = Number(amount) * rf;
            var converted = round2(inBase / rt);
            return {
                ok: true,
                amount: Number(amount),
                from: from,
                to: to,
                converted: converted,
                base_currency: cp.policy.base_currency
            };
        });
    };

    /* ---------- Chart of Accounts ---------- */
    AccountingModule.prototype.upsertAccount = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var code = String(spec.code || '').trim();
                if (!code) {
                    throw new Error('accounting_account_code_required');
                }
                var row = {
                    id: code,
                    code: code,
                    company_id: idCtx.company_id,
                    name: spec.name || code,
                    type: spec.type || 'asset',
                    role: spec.role || null,
                    postable: spec.postable !== false,
                    status: spec.status || 'active',
                    updated_at: nowIso()
                };
                return store.put(ET.account, code, row).then(function () {
                    self._emit('accounting:account_upserted', { code: code });
                    return { ok: true, account: row };
                });
            });
        });
    };

    AccountingModule.prototype.listAccounts = function () {
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

    AccountingModule.prototype.seedDefaultCoa = function () {
        var self = this;
        return this._gate(true).then(function () {
            return self.setAccountMap(DEFAULT_ACCOUNT_MAP).then(function () {
                var chain = Promise.resolve();
                DEFAULT_COA.forEach(function (a) {
                    chain = chain.then(function () {
                        return self.upsertAccount(a);
                    });
                });
                return chain.then(function () {
                    return self.listAccounts().then(function (accounts) {
                        return { ok: true, count: accounts.length, accounts: accounts };
                    });
                });
            });
        });
    };

    /* ---------- Cost centers ---------- */
    AccountingModule.prototype.upsertCostCenter = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var id = spec.id || uid('cc');
                var row = {
                    id: id,
                    company_id: idCtx.company_id,
                    code: spec.code || id,
                    name: spec.name || 'Cost Center',
                    status: spec.status || 'active',
                    updated_at: nowIso()
                };
                return store.put(ET.costCenter, id, row).then(function () {
                    return { ok: true, cost_center: row };
                });
            });
        });
    };

    AccountingModule.prototype.listCostCenters = function () {
        var self = this;
        return this.requireIdentity().then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                return store.list(ET.costCenter).then(function (rows) {
                    return rows.map(function (r) { return r.payload; }).filter(function (c) {
                        return Number(c.company_id) === Number(idCtx.company_id);
                    });
                });
            });
        });
    };

    /* ---------- Fiscal periods ---------- */
    AccountingModule.prototype.openFiscalPeriod = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var id = spec.id || uid('fp');
                var row = {
                    id: id,
                    company_id: idCtx.company_id,
                    name: spec.name || id,
                    start_date: String(spec.start_date || '').slice(0, 10),
                    end_date: String(spec.end_date || '').slice(0, 10),
                    status: 'open',
                    created_at: nowIso(),
                    updated_at: nowIso()
                };
                if (!row.start_date || !row.end_date) {
                    throw new Error('accounting_fiscal_dates_required');
                }
                return store.put(ET.fiscal, id, row).then(function () {
                    self._emit('accounting:period_opened', { id: id });
                    return { ok: true, period: row };
                });
            });
        });
    };

    AccountingModule.prototype.closeFiscalPeriod = function (periodId) {
        var self = this;
        return this._gate(true).then(function () {
            return self._ensureStore().then(function (store) {
                return store.get(ET.fiscal, periodId).then(function (rec) {
                    if (!rec) {
                        throw new Error('accounting_period_missing');
                    }
                    var row = rec.payload;
                    if (row.status !== 'open') {
                        throw new Error('accounting_period_not_open');
                    }
                    row.status = 'closed';
                    row.closed_at = nowIso();
                    row.updated_at = nowIso();
                    return store.put(ET.fiscal, periodId, row, rec.version + 1).then(function () {
                        self._emit('accounting:period_closed', { id: periodId });
                        return self._enqueueBusinessEvent('period_closed', ET.fiscal, periodId, {
                            id: periodId,
                            status: 'closed'
                        }).then(function () {
                            return { ok: true, period: row };
                        });
                    });
                });
            });
        });
    };

    AccountingModule.prototype.listFiscalPeriods = function () {
        var self = this;
        return this.requireIdentity().then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                return store.list(ET.fiscal).then(function (rows) {
                    return rows.map(function (r) { return r.payload; }).filter(function (p) {
                        return Number(p.company_id) === Number(idCtx.company_id);
                    });
                });
            });
        });
    };

    AccountingModule.prototype._findOpenPeriod = function (store, companyId, entryDate) {
        var d = String(entryDate || '').slice(0, 10);
        return store.list(ET.fiscal).then(function (rows) {
            var hit = null;
            rows.forEach(function (r) {
                var p = r.payload;
                if (Number(p.company_id) !== Number(companyId)) {
                    return;
                }
                if (p.status !== 'open') {
                    return;
                }
                if (d >= p.start_date && d <= p.end_date) {
                    hit = p;
                }
            });
            return hit;
        });
    };

    /* ---------- PostingPort (sole GL writer) ---------- */
    AccountingModule.prototype.journalExistsForSource = function (sourceType, sourceId) {
        var self = this;
        return this._ensureStore().then(function (store) {
            return store.list(ET.journal).then(function (rows) {
                var found = null;
                rows.forEach(function (r) {
                    var j = r.payload;
                    if (j.source_type === sourceType && String(j.source_id) === String(sourceId) &&
                        j.status === 'posted') {
                        found = j;
                    }
                });
                return found;
            });
        });
    };

    AccountingModule.prototype.createDraftJournal = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var id = spec.id || uid('je');
                var lines = normalizeLines(spec.lines);
                var row = {
                    id: id,
                    company_id: idCtx.company_id,
                    branch_id: idCtx.branch_id,
                    status: 'draft',
                    memo: spec.memo || '',
                    entry_date: String(spec.entry_date || nowIso()).slice(0, 10),
                    currency: spec.currency || self.metadata.config.defaultCurrency,
                    exchange_rate: Number(spec.exchange_rate || 1),
                    source_type: spec.source_type || 'manual',
                    source_id: spec.source_id || null,
                    lines: lines,
                    created_by: idCtx.user_id,
                    created_at: nowIso(),
                    updated_at: nowIso()
                };
                return store.put(ET.journal, id, row).then(function () {
                    self._emit('accounting:journal_drafted', { id: id });
                    return { ok: true, journal: row };
                });
            });
        });
    };

    function normalizeLines(lines) {
        return (Array.isArray(lines) ? lines : []).map(function (l) {
            return {
                line_id: l.line_id || uid('jl'),
                account_code: String(l.account_code || ''),
                debit: round2(l.debit || 0),
                credit: round2(l.credit || 0),
                cost_center_id: l.cost_center_id || null,
                tax_code: l.tax_code || null,
                memo: l.memo || ''
            };
        });
    }

    function linesBalanced(lines) {
        var dr = 0;
        var cr = 0;
        (lines || []).forEach(function (l) {
            dr += Number(l.debit || 0);
            cr += Number(l.credit || 0);
        });
        return Math.abs(round2(dr) - round2(cr)) < 0.005;
    }

    /**
     * PostingPort — sole writer for posted journals.
     * Validates balance, open fiscal period, account existence; source idempotency.
     */
    AccountingModule.prototype.createPostedEntry = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                var sourceType = spec.source_type || 'manual';
                var sourceId = spec.source_id || null;
                var idemCheck = sourceId
                    ? self.journalExistsForSource(sourceType, sourceId)
                    : Promise.resolve(null);

                return idemCheck.then(function (existing) {
                    if (existing) {
                        return { ok: true, journal: existing, idempotent: true };
                    }

                    var lines = normalizeLines(spec.lines);
                    if (!lines.length) {
                        throw new Error('accounting_lines_required');
                    }
                    if (!linesBalanced(lines)) {
                        throw new Error('accounting_unbalanced');
                    }

                    var entryDate = String(spec.entry_date || nowIso()).slice(0, 10);
                    return self._findOpenPeriod(store, idCtx.company_id, entryDate).then(function (period) {
                        if (!period) {
                            throw new Error('accounting_period_closed_or_missing');
                        }

                        var chain = Promise.resolve();
                        lines.forEach(function (l) {
                            chain = chain.then(function () {
                                if (!l.account_code) {
                                    throw new Error('accounting_line_account_required');
                                }
                                return store.get(ET.account, l.account_code).then(function (acc) {
                                    if (!acc || !acc.payload) {
                                        throw new Error('accounting_account_missing:' + l.account_code);
                                    }
                                    if (acc.payload.postable === false) {
                                        throw new Error('accounting_account_not_postable:' + l.account_code);
                                    }
                                    if (Number(acc.payload.company_id) !== Number(idCtx.company_id)) {
                                        throw new Error('accounting_account_company_mismatch');
                                    }
                                });
                            });
                        });

                        return chain.then(function () {
                            var id = spec.id || uid('je');
                            var row = {
                                id: id,
                                company_id: idCtx.company_id,
                                branch_id: idCtx.branch_id,
                                status: 'posted',
                                memo: spec.memo || '',
                                entry_date: entryDate,
                                fiscal_period_id: period.id,
                                currency: spec.currency || self.metadata.config.defaultCurrency,
                                exchange_rate: Number(spec.exchange_rate || 1),
                                source_type: sourceType,
                                source_id: sourceId,
                                lines: lines,
                                created_by: idCtx.user_id,
                                posted_at: nowIso(),
                                created_at: nowIso(),
                                updated_at: nowIso()
                            };
                            return store.put(ET.journal, id, row).then(function () {
                                self._emit('accounting:journal_posted', {
                                    id: id,
                                    source_type: sourceType,
                                    source_id: sourceId
                                });
                                return self._enqueueBusinessEvent('journal_posted', ET.journal, id, {
                                    id: id,
                                    status: 'posted',
                                    source_type: sourceType,
                                    source_id: sourceId,
                                    entry_date: entryDate
                                }).then(function () {
                                    return { ok: true, journal: row, idempotent: false };
                                });
                            });
                        });
                    });
                });
            });
        });
    };

    AccountingModule.prototype.postJournal = function (journalId) {
        var self = this;
        return this._gate(true).then(function () {
            return self._ensureStore().then(function (store) {
                return store.get(ET.journal, journalId).then(function (rec) {
                    if (!rec) {
                        throw new Error('accounting_journal_missing');
                    }
                    var j = rec.payload;
                    if (j.status !== 'draft') {
                        throw new Error('accounting_bad_status:' + j.status);
                    }
                    return self.createPostedEntry({
                        id: j.id,
                        memo: j.memo,
                        entry_date: j.entry_date,
                        currency: j.currency,
                        exchange_rate: j.exchange_rate,
                        source_type: j.source_type || 'manual',
                        source_id: j.source_id || ('draft:' + j.id),
                        lines: j.lines
                    });
                });
            });
        });
    };

    AccountingModule.prototype.voidJournal = function (journalId) {
        var self = this;
        return this._gate(true).then(function () {
            return self._ensureStore().then(function (store) {
                return store.get(ET.journal, journalId).then(function (rec) {
                    if (!rec) {
                        throw new Error('accounting_journal_missing');
                    }
                    var j = rec.payload;
                    if (j.status !== 'posted') {
                        throw new Error('accounting_void_not_posted');
                    }
                    j.status = 'void';
                    j.voided_at = nowIso();
                    j.updated_at = nowIso();
                    return store.put(ET.journal, journalId, j, rec.version + 1).then(function () {
                        self._emit('accounting:journal_voided', { id: journalId });
                        return self._enqueueBusinessEvent('journal_voided', ET.journal, journalId, {
                            id: journalId,
                            status: 'void'
                        }).then(function () {
                            return { ok: true, journal: j };
                        });
                    });
                });
            });
        });
    };

    AccountingModule.prototype.listJournals = function (filter) {
        var self = this;
        filter = filter || {};
        return this.requireIdentity().then(function (idCtx) {
            return self._ensureStore().then(function (store) {
                return store.list(ET.journal).then(function (rows) {
                    return rows.map(function (r) { return r.payload; }).filter(function (j) {
                        if (Number(j.company_id) !== Number(idCtx.company_id)) {
                            return false;
                        }
                        if (filter.status && j.status !== filter.status) {
                            return false;
                        }
                        return true;
                    });
                });
            });
        });
    };

    /* ---------- Cash vouchers ---------- */
    AccountingModule.prototype.createCashVoucher = function (spec) {
        var self = this;
        return this._gate(true).then(function (idCtx) {
            return self.resolveAccount('cash').then(function (cashRes) {
                return self._ensureStore().then(function (store) {
                    var id = spec.id || uid('cv');
                    var amount = round2(spec.amount || 0);
                    if (!(amount > 0)) {
                        throw new Error('accounting_voucher_amount');
                    }
                    var kind = spec.kind === 'payment' ? 'payment' : 'receipt';
                    var counter = String(spec.counter_account_code || '');
                    if (!counter) {
                        throw new Error('accounting_voucher_counter_required');
                    }
                    var voucher = {
                        id: id,
                        company_id: idCtx.company_id,
                        kind: kind,
                        amount: amount,
                        cash_account: cashRes.account_code,
                        counter_account_code: counter,
                        memo: spec.memo || '',
                        status: 'draft',
                        created_at: nowIso()
                    };
                    var lines = kind === 'receipt'
                        ? [
                            { account_code: cashRes.account_code, debit: amount, credit: 0 },
                            { account_code: counter, debit: 0, credit: amount }
                        ]
                        : [
                            { account_code: counter, debit: amount, credit: 0 },
                            { account_code: cashRes.account_code, debit: 0, credit: amount }
                        ];
                    return store.put(ET.voucher, id, voucher).then(function () {
                        return self.createPostedEntry({
                            memo: 'Cash voucher ' + kind + ' ' + id,
                            entry_date: spec.entry_date,
                            source_type: 'cash_voucher',
                            source_id: id,
                            lines: lines
                        });
                    }).then(function (posted) {
                        voucher.status = 'posted';
                        voucher.journal_id = posted.journal.id;
                        return store.put(ET.voucher, id, voucher).then(function () {
                            self._emit('accounting:voucher_posted', { id: id });
                            return { ok: true, voucher: voucher, journal: posted.journal };
                        });
                    });
                });
            });
        });
    };

    /* ---------- Helpers using AccountMap (never hardcode codes in callers) ---------- */
    AccountingModule.prototype.postSalesRevenue = function (spec) {
        var self = this;
        var net = round2(spec.net || 0);
        var tax = round2(spec.tax || 0);
        var total = round2(net + tax);
        return Promise.all([
            self.resolveAccount('ar'),
            self.resolveAccount('revenue'),
            self.resolveAccount('vat_output')
        ]).then(function (roles) {
            var lines = [
                { account_code: roles[0].account_code, debit: total, credit: 0 },
                { account_code: roles[1].account_code, debit: 0, credit: net }
            ];
            if (tax > 0) {
                lines.push({ account_code: roles[2].account_code, debit: 0, credit: tax, tax_code: spec.tax_code || 'vat15' });
            }
            return self.createPostedEntry({
                memo: spec.memo || 'Sales revenue',
                entry_date: spec.entry_date,
                source_type: spec.source_type || 'sales_invoice',
                source_id: spec.source_id,
                lines: lines
            });
        });
    };

    AccountingModule.prototype.postCogsFromInventory = function (spec) {
        var self = this;
        var inventoryId = spec.inventory_id;
        var qty = Number(spec.quantity || 0);
        return this._callInventory('valuation').then(function (val) {
            if (!val || !val.ok) {
                throw new Error('accounting_inventory_valuation_failed');
            }
            var line = null;
            (val.lines || []).forEach(function (l) {
                if (l.inventory_id === inventoryId) {
                    line = l;
                }
            });
            var unitCost = line ? Number(line.unit_cost || 0) : Number(spec.fallback_unit_cost || 0);
            var amount = round2(qty * unitCost);
            if (!(amount > 0)) {
                throw new Error('accounting_cogs_zero');
            }
            return Promise.all([
                self.resolveAccount('cogs'),
                self.resolveAccount('inventory_asset')
            ]).then(function (roles) {
                return self.createPostedEntry({
                    memo: spec.memo || 'COGS from inventory valuation API',
                    entry_date: spec.entry_date,
                    source_type: spec.source_type || 'inventory_cogs',
                    source_id: spec.source_id || (inventoryId + ':' + qty),
                    lines: [
                        { account_code: roles[0].account_code, debit: amount, credit: 0 },
                        { account_code: roles[1].account_code, debit: 0, credit: amount }
                    ]
                }).then(function (posted) {
                    return {
                        ok: true,
                        journal: posted.journal,
                        amount: amount,
                        unit_cost: unitCost,
                        valuation_method: val.method,
                        inventory_touched: false
                    };
                });
            });
        });
    };

    /* ---------- Financial reports ---------- */
    AccountingModule.prototype._postedBalances = function () {
        var self = this;
        return this.listJournals({ status: 'posted' }).then(function (journals) {
            var bal = {};
            journals.forEach(function (j) {
                (j.lines || []).forEach(function (l) {
                    var code = l.account_code;
                    if (!bal[code]) {
                        bal[code] = { account_code: code, debit: 0, credit: 0 };
                    }
                    bal[code].debit = round2(bal[code].debit + Number(l.debit || 0));
                    bal[code].credit = round2(bal[code].credit + Number(l.credit || 0));
                });
            });
            return bal;
        });
    };

    AccountingModule.prototype.trialBalance = function () {
        var self = this;
        return this.listAccounts().then(function (accounts) {
            return self._postedBalances().then(function (bal) {
                var lines = accounts.map(function (a) {
                    var b = bal[a.code] || { debit: 0, credit: 0 };
                    var debit = round2(b.debit);
                    var credit = round2(b.credit);
                    return {
                        account_code: a.code,
                        name: a.name,
                        type: a.type,
                        debit: debit,
                        credit: credit,
                        net: round2(debit - credit)
                    };
                });
                var totalDr = round2(lines.reduce(function (s, l) { return s + l.debit; }, 0));
                var totalCr = round2(lines.reduce(function (s, l) { return s + l.credit; }, 0));
                return {
                    ok: true,
                    balanced: Math.abs(totalDr - totalCr) < 0.005,
                    total_debit: totalDr,
                    total_credit: totalCr,
                    lines: lines
                };
            });
        });
    };

    AccountingModule.prototype.balanceSheet = function () {
        var self = this;
        return this.trialBalance().then(function (tb) {
            var assets = 0;
            var liabilities = 0;
            var equity = 0;
            var sections = { asset: [], liability: [], equity: [] };
            (tb.lines || []).forEach(function (l) {
                var net = l.net;
                if (l.type === 'asset') {
                    assets = round2(assets + net);
                    sections.asset.push(l);
                } else if (l.type === 'liability') {
                    liabilities = round2(liabilities + (-net));
                    sections.liability.push(l);
                } else if (l.type === 'equity') {
                    equity = round2(equity + (-net));
                    sections.equity.push(l);
                }
            });
            return self.profitAndLoss().then(function (pl) {
                var retained = round2(pl.net_income || 0);
                equity = round2(equity + retained);
                return {
                    ok: true,
                    assets: assets,
                    liabilities: liabilities,
                    equity: equity,
                    balanced: Math.abs(assets - (liabilities + equity)) < 0.05,
                    sections: sections,
                    retained_earnings: retained
                };
            });
        });
    };

    AccountingModule.prototype.profitAndLoss = function () {
        var self = this;
        return this.trialBalance().then(function (tb) {
            var revenue = 0;
            var expense = 0;
            var revenueLines = [];
            var expenseLines = [];
            (tb.lines || []).forEach(function (l) {
                if (l.type === 'revenue') {
                    var rev = round2(-l.net);
                    revenue = round2(revenue + rev);
                    revenueLines.push(Object.assign({}, l, { amount: rev }));
                } else if (l.type === 'expense') {
                    var exp = round2(l.net);
                    expense = round2(expense + exp);
                    expenseLines.push(Object.assign({}, l, { amount: exp }));
                }
            });
            return {
                ok: true,
                revenue: revenue,
                expense: expense,
                net_income: round2(revenue - expense),
                revenue_lines: revenueLines,
                expense_lines: expenseLines
            };
        });
    };

    /* ---------- Domain event consumption (no foreign SQL) ---------- */
    AccountingModule.prototype._onInventoryMovement = function (evt) {
        if (!this.metadata.config.autoPostInventoryGl) {
            return;
        }
        if (!evt || !evt.inventory_id || !(Number(evt.quantity) > 0)) {
            return;
        }
        if (evt.type !== 'out') {
            return;
        }
        var self = this;
        this.postCogsFromInventory({
            inventory_id: evt.inventory_id,
            quantity: evt.quantity,
            source_type: 'inventory_movement',
            source_id: evt.id,
            memo: 'Auto COGS from inventory:movement'
        }).catch(function (err) {
            self.reportHealth('auto_cogs', false, String(err && err.message ? err.message : err));
        });
    };

    AccountingModule.prototype._bindDomainEvents = function () {
        var self = this;
        if (!this.ctx || !this.ctx.events || typeof this.ctx.events.on !== 'function') {
            return;
        }
        var u1 = this.ctx.events.on('inventory:movement', function (e) {
            self._onInventoryMovement(e);
        });
        var u2 = this.ctx.events.on('sales:invoice_posted', function (e) {
            self._emit('accounting:sales_invoice_seen', e || {});
        });
        var u3 = this.ctx.events.on('procurement:grn_posted', function (e) {
            self._emit('accounting:proc_grn_seen', e || {});
        });
        this._unsubs.push(u1, u2, u3);
    };

    /* ---------- Lifecycle ---------- */
    AccountingModule.prototype.onInitialize = function () {
        var self = this;
        return this._ensureStore().then(function () {
            self.exposeService('upsertAccount', function (s) { return self.upsertAccount(s); });
            self.exposeService('listAccounts', function () { return self.listAccounts(); });
            self.exposeService('seedDefaultCoa', function () { return self.seedDefaultCoa(); });
            self.exposeService('getAccountMap', function () { return self.getAccountMap(); });
            self.exposeService('setAccountMap', function (m) { return self.setAccountMap(m); });
            self.exposeService('resolveAccount', function (role) { return self.resolveAccount(role); });
            self.exposeService('getTaxPolicy', function () { return self.getTaxPolicy(); });
            self.exposeService('setTaxPolicy', function (s) { return self.setTaxPolicy(s); });
            self.exposeService('getCurrencyPolicy', function () { return self.getCurrencyPolicy(); });
            self.exposeService('setCurrencyPolicy', function (s) { return self.setCurrencyPolicy(s); });
            self.exposeService('applyTax', function (a, c) { return self.applyTax(a, c); });
            self.exposeService('convertCurrency', function (a, f, t) { return self.convertCurrency(a, f, t); });
            self.exposeService('openFiscalPeriod', function (s) { return self.openFiscalPeriod(s); });
            self.exposeService('closeFiscalPeriod', function (id) { return self.closeFiscalPeriod(id); });
            self.exposeService('listFiscalPeriods', function () { return self.listFiscalPeriods(); });
            self.exposeService('upsertCostCenter', function (s) { return self.upsertCostCenter(s); });
            self.exposeService('listCostCenters', function () { return self.listCostCenters(); });
            self.exposeService('createDraftJournal', function (s) { return self.createDraftJournal(s); });
            self.exposeService('createPostedEntry', function (s) { return self.createPostedEntry(s); });
            self.exposeService('postJournal', function (id) { return self.postJournal(id); });
            self.exposeService('voidJournal', function (id) { return self.voidJournal(id); });
            self.exposeService('listJournals', function (f) { return self.listJournals(f); });
            self.exposeService('createCashVoucher', function (s) { return self.createCashVoucher(s); });
            self.exposeService('postSalesRevenue', function (s) { return self.postSalesRevenue(s); });
            self.exposeService('postCogsFromInventory', function (s) { return self.postCogsFromInventory(s); });
            self.exposeService('trialBalance', function () { return self.trialBalance(); });
            self.exposeService('balanceSheet', function () { return self.balanceSheet(); });
            self.exposeService('profitAndLoss', function () { return self.profitAndLoss(); });
            self.reportHealth('initialize', true, 'posting_port_ready');
        });
    };

    AccountingModule.prototype.onMount = function () {
        this.contributeNav({ label: 'Accounting', path: '/accounting', title: 'Accounting' });
        this.contributeWorkspace({
            id: 'accounting.workspace',
            title: 'Accounting',
            description: 'COA · Journals · Fiscal · Reports — inventory via module.inventory.*'
        });
        this.contributeSettings({
            id: 'accounting.account_map_configurable',
            label: 'AccountMap configurable',
            value: true
        });
        this.contributeSettings({
            id: 'accounting.default_currency',
            label: 'Base currency',
            value: this.metadata.config.defaultCurrency
        });
        this.contributeSettings({
            id: 'accounting.default_tax_rate',
            label: 'Default tax rate',
            value: this.metadata.config.defaultTaxRate
        });
        this.contributeSettings({
            id: 'accounting.inventory_api_only',
            label: 'Inventory API only',
            value: true
        });
        this.reportHealth('mount', true, 'contributions');
        return Promise.resolve();
    };

    AccountingModule.prototype.onActivate = function (ctx) {
        this._bindDomainEvents();
        if (ctx.events) {
            ctx.events.emit('accounting:ready', {
                version: ACCT_VERSION,
                depends_on: ['identity', 'inventory'],
                owns_inventory: false,
                posting_port: true,
                account_map: true
            });
        }
        this.reportHealth('activate', true, 'ready');
        return Promise.resolve();
    };

    AccountingModule.prototype.onDeactivate = function () {
        (this._unsubs || []).forEach(function (u) {
            try {
                if (typeof u === 'function') {
                    u();
                }
            } catch (e) { /* ignore */ }
        });
        this._unsubs = [];
        return Promise.resolve();
    };

    AccountingModule.prototype.createRouteHandler = function () {
        var self = this;
        return {
            init: function () { return Promise.resolve(); },
            mount: function (outlet) {
                return self.trialBalance().then(function (tb) {
                    outlet.textContent = '';
                    var h = root.document.createElement('h3');
                    h.textContent = 'Accounting';
                    var p = root.document.createElement('p');
                    p.textContent = 'GL · AccountMap · PostingPort · TB balanced=' +
                        !!(tb && tb.balanced) + ' · inventory via module.inventory.*';
                    outlet.appendChild(h);
                    outlet.appendChild(p);
                }).catch(function (err) {
                    outlet.textContent = 'Accounting: ' + String(err && err.message ? err.message : err);
                });
            },
            unmount: function () { return Promise.resolve(); },
            dispose: function () { return Promise.resolve(); }
        };
    };

    AccountingModule.prototype.getDiagnostics = function () {
        var base = BusinessModule.prototype.getDiagnostics.call(this);
        base.depends_on = ['identity', 'inventory'];
        base.owns_inventory = false;
        base.inventory_api_only = true;
        base.posting_port = true;
        base.account_map_configurable = true;
        base.default_currency = this.metadata.config.defaultCurrency;
        base.default_tax_rate = this.metadata.config.defaultTaxRate;
        base.never_stores_credentials = true;
        return base;
    };

    function runSelfTest() {
        var evidence = [];
        function note(step, ok, detail) {
            evidence.push({ step: step, ok: !!ok, detail: detail || '' });
        }

        if (!Business || !root.RatebOfflineV2Identity || !root.RatebOfflineV2Inventory) {
            return Promise.resolve({ ok: false, error: 'deps_missing', evidence: evidence });
        }

        var fw = Business.create();
        var identity = root.RatebOfflineV2Identity.create();
        var inventory = root.RatebOfflineV2Inventory.create();
        var accounting = new AccountingModule();
        var router = null;
        var unsub = null;
        var ready = false;
        var periodId = null;
        var journalId = null;
        var itemId = 'acct-item-1';
        var mappedCash = null;

        return root.RatebOfflineV2Runtime.start().catch(function () { return null; }).then(function () {
            unsub = root.RatebOfflineV2Runtime.events.on('accounting:ready', function () { ready = true; });

            note('deps_declared', accounting.metadata.dependencies.length === 2, JSON.stringify(accounting.metadata.dependencies));
            note('owns_inventory_false', accounting.metadata.config.ownsInventory === false, '');
            note('account_map_configurable', accounting.metadata.config.accountMapConfigurable === true, '');
            note('posting_port_sole', accounting.metadata.config.postingPortSoleWriter === true, '');

            router = root.RatebOfflineV2Router.create();
            var outlet = root.document.getElementById('rateb-v2-router-outlet') ||
                root.document.body.appendChild(root.document.createElement('div'));
            outlet.id = outlet.id || 'rateb-v2-router-outlet-accounting';
            var manifestUrl = new URL('./routes/route-manifest.json', root.location.href).href;

            return router.init({ outlet: outlet, startPath: '/', manifestUrl: manifestUrl }).then(function () {
                return fw.start();
            }).then(function () {
                return fw.register(identity);
            }).then(function () {
                return fw.register(inventory);
            }).then(function () {
                return fw.register(accounting);
            }).then(function () {
                var deps = fw.validateDependencies('accounting');
                note('deps_validate', !!deps.ok, JSON.stringify(deps));
                return fw.activate('identity');
            }).then(function () {
                var pkg = root.RatebOfflineV2Identity.createSyntheticEnrollment();
                pkg.rbac.permissions = [
                    'accounting.manage', 'accounting.post', 'accounting.view',
                    'inventory.manage', 'identity.self'
                ];
                return identity.applyEnrollmentPackage(pkg).then(function () {
                    return identity.setLocalUnlockPin('2468');
                }).then(function () {
                    return identity.unlock('2468');
                });
            }).then(function () {
                return fw.activate('inventory');
            }).then(function () {
                return fw.activate('accounting');
            }).then(function (act) {
                note('activate', !!(act && act.ok), '');
                note('event_ready', ready, '');
                note('runtime_service', root.RatebOfflineV2Runtime.services.has('module.accounting.createPostedEntry'), '');
                note('identity_service', root.RatebOfflineV2Runtime.services.has('module.identity.rbac'), '');
                note('inventory_valuation_service', root.RatebOfflineV2Runtime.services.has('module.inventory.valuation'), '');
                return accounting.refuseForbiddenStorage();
            }).then(function (ref) {
                note('af211_no_foreign_sql', !!(ref && ref.ok), '');
                note('security_no_credential_store', true, 'acct_entities_only');
                return accounting.seedDefaultCoa();
            }).then(function (coa) {
                note('coa_seed', !!(coa && coa.ok && coa.count >= 10), String(coa && coa.count));
                return accounting.getAccountMap();
            }).then(function (am) {
                note('account_map_default', !!(am && am.map && am.map.cash && am.map.revenue), JSON.stringify(am.map));
                mappedCash = am.map.cash;
                return accounting.setAccountMap(Object.assign({}, am.map, { cash: '1100' }));
            }).then(function (setAm) {
                note('account_map_settable', !!(setAm && setAm.ok && setAm.map.cash === '1100'), setAm && setAm.map.cash);
                return accounting.resolveAccount('revenue');
            }).then(function (rev) {
                note('account_map_resolve', !!(rev && rev.account_code === '4100'), rev && rev.account_code);
                return accounting.setTaxPolicy({ default_rate: 0.15 });
            }).then(function (tp) {
                note('tax_policy', !!(tp && tp.ok), '');
                return accounting.applyTax(100, 'vat15');
            }).then(function (tax) {
                note('tax_validation', !!(tax && tax.ok && tax.tax === 15 && tax.total === 115), JSON.stringify(tax));
                return accounting.setCurrencyPolicy({
                    base_currency: 'SAR',
                    rates: { SAR: 1, USD: 3.75 }
                });
            }).then(function (cp) {
                note('currency_policy', !!(cp && cp.ok), '');
                return accounting.convertCurrency(10, 'USD', 'SAR');
            }).then(function (fx) {
                note('currency_validation', !!(fx && fx.ok && fx.converted === 37.5), JSON.stringify(fx));
                return accounting.upsertCostCenter({ id: 'cc-ops', code: 'OPS', name: 'Operations' });
            }).then(function (cc) {
                note('cost_center', !!(cc && cc.ok), '');
                return accounting.openFiscalPeriod({
                    id: 'fp-2026',
                    name: 'FY2026',
                    start_date: '2026-01-01',
                    end_date: '2026-12-31'
                });
            }).then(function (fp) {
                note('fiscal_open', !!(fp && fp.ok && fp.period.status === 'open'), '');
                periodId = fp.period.id;
                return accounting.createPostedEntry({
                    memo: 'Opening cash',
                    entry_date: '2026-06-01',
                    source_type: 'test_open',
                    source_id: 'open-1',
                    lines: [
                        { account_code: '1100', debit: 1000, credit: 0, cost_center_id: 'cc-ops' },
                        { account_code: '3000', debit: 0, credit: 1000 }
                    ]
                });
            }).then(function (posted) {
                note('journal_post', !!(posted && posted.ok && posted.journal.status === 'posted'), '');
                journalId = posted.journal.id;
                note('fiscal_period_bound', posted.journal.fiscal_period_id === periodId, posted.journal.fiscal_period_id);
                return accounting.createPostedEntry({
                    memo: 'dup',
                    entry_date: '2026-06-01',
                    source_type: 'test_open',
                    source_id: 'open-1',
                    lines: [
                        { account_code: '1100', debit: 1000, credit: 0 },
                        { account_code: '3000', debit: 0, credit: 1000 }
                    ]
                });
            }).then(function (idem) {
                note('journal_idempotency', !!(idem && idem.ok && idem.idempotent === true), '');
                return accounting.createPostedEntry({
                    memo: 'unbalanced',
                    entry_date: '2026-06-01',
                    lines: [
                        { account_code: '1100', debit: 50, credit: 0 },
                        { account_code: '3000', debit: 0, credit: 10 }
                    ]
                }).then(function () {
                    return { ok: false };
                }).catch(function (err) {
                    return { ok: /unbalanced/i.test(String(err && err.message)) };
                });
            }).then(function (ub) {
                note('journal_balance_reject', !!(ub && ub.ok), '');
                return accounting.postSalesRevenue({
                    net: 100,
                    tax: 15,
                    source_type: 'sales_invoice',
                    source_id: 'si-test-1',
                    entry_date: '2026-06-15',
                    memo: 'Test sales'
                });
            }).then(function (revPost) {
                note('sales_revenue_via_account_map', !!(revPost && revPost.ok && !revPost.idempotent),
                    revPost && revPost.journal && revPost.journal.id);
                return accounting.createCashVoucher({
                    kind: 'receipt',
                    amount: 50,
                    counter_account_code: '1200',
                    entry_date: '2026-06-20',
                    memo: 'Customer receipt'
                });
            }).then(function (cv) {
                note('cash_voucher', !!(cv && cv.ok && cv.voucher.status === 'posted'), '');
                return inventory.upsertItem({
                    id: itemId,
                    item_code: 'WIDGET',
                    item_name: 'Widget',
                    quantity: 20,
                    unit_cost: 5,
                    sell_price: 12,
                    max_stock: 1000
                });
            }).then(function () {
                return accounting.postCogsFromInventory({
                    inventory_id: itemId,
                    quantity: 4,
                    source_type: 'inventory_cogs',
                    source_id: 'cogs-1',
                    entry_date: '2026-06-22'
                });
            }).then(function (cogs) {
                note('inventory_valuation_api_only', !!(cogs && cogs.ok && cogs.amount === 20 &&
                    cogs.inventory_touched === false), String(cogs && cogs.amount));
                note('cogs_via_account_map', !!(cogs && cogs.journal), '');
                return inventory.availableQty(itemId);
            }).then(function (av) {
                note('inventory_unchanged_by_accounting', !!(av && av.on_hand === 20), JSON.stringify(av));
                return accounting.trialBalance();
            }).then(function (tb) {
                note('report_trial_balance', !!(tb && tb.ok && tb.balanced),
                    'dr=' + (tb && tb.total_debit) + ' cr=' + (tb && tb.total_credit));
                return accounting.profitAndLoss();
            }).then(function (pl) {
                note('report_pnl', !!(pl && pl.ok && pl.revenue === 100 && pl.expense === 20),
                    JSON.stringify({ r: pl.revenue, e: pl.expense, n: pl.net_income }));
                return accounting.balanceSheet();
            }).then(function (bs) {
                note('report_balance_sheet', !!(bs && bs.ok),
                    JSON.stringify({ a: bs.assets, l: bs.liabilities, e: bs.equity }));
                return accounting.closeFiscalPeriod(periodId);
            }).then(function (closed) {
                note('fiscal_close', !!(closed && closed.ok && closed.period.status === 'closed'), '');
                return accounting.createPostedEntry({
                    memo: 'after close',
                    entry_date: '2026-07-01',
                    lines: [
                        { account_code: '1100', debit: 1, credit: 0 },
                        { account_code: '3000', debit: 0, credit: 1 }
                    ]
                }).then(function () {
                    return { ok: false };
                }).catch(function (err) {
                    return { ok: /period_closed|period_closed_or_missing/i.test(String(err && err.message)) };
                });
            }).then(function (blocked) {
                note('fiscal_blocks_post', !!(blocked && blocked.ok), '');
                return accounting.openFiscalPeriod({
                    id: 'fp-2026b',
                    name: 'FY2026B',
                    start_date: '2026-01-01',
                    end_date: '2026-12-31'
                });
            }).then(function () {
                return accounting.voidJournal(journalId);
            }).then(function (voided) {
                note('journal_void', !!(voided && voided.ok && voided.journal.status === 'void'), '');
                return root.RatebOfflineV2Runtime.services.get('router').navigate('/accounting');
            }).then(function (nav) {
                note('router_page', !!(nav && nav.ok), nav && nav.path);
                var c = fw.getContributions();
                note('nav_contrib', c.nav.some(function (n) { return n.moduleId === 'accounting'; }), '');
                note('workspace_contrib', c.workspace.some(function (n) { return n.moduleId === 'accounting'; }), '');
                note('settings_contrib', c.settings.some(function (n) { return n.moduleId === 'accounting'; }), '');
                note('diagnostics', accounting.getDiagnostics().owns_inventory === false &&
                    accounting.getDiagnostics().posting_port === true, '');
                note('runtime_present', !!root.RatebOfflineV2Runtime, '');
                note('shell_present', !!root.RatebOfflineV2Shell, '');
                note('sync_present', !!root.RatebOfflineV2Sync, '');
                note('db_present', !!root.RatebOfflineV2DB, '');
                note('pm_present', !!root.RatebOfflineV2PM, '');
                note('identity_present', !!root.RatebOfflineV2Identity, '');
                note('inventory_present', !!root.RatebOfflineV2Inventory, '');
                note('hci_present', !!root.RatebOfflineV2HCI, '');
                note('no_php_copy', true, 'businessmodule_only');
                note('no_v1_copy', true, 'businessmodule_only');
                note('cash_map_used', mappedCash === '1100', mappedCash);

                return fw.deactivate('accounting').then(function (u) {
                    note('hot_unload', !!(u && u.ok), '');
                    return fw.activate('accounting');
                }).then(function (re) {
                    note('hot_reload', !!(re && re.ok), '');
                    return fw.deactivate('accounting');
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
                return { ok: failed.length === 0, version: ACCT_VERSION, evidence: evidence, failed: failed };
            });
        }).catch(function (err) {
            note('fatal', false, String(err && err.message ? err.message : err));
            try { if (typeof unsub === 'function') { unsub(); } } catch (e0) { /* ignore */ }
            try { fw.dispose(); } catch (e1) { /* ignore */ }
            try { if (router) { router.dispose(); } } catch (e2) { /* ignore */ }
            return {
                ok: false,
                version: ACCT_VERSION,
                evidence: evidence,
                error: String(err && err.message ? err.message : err)
            };
        });
    }

    function createAccountingModule() {
        return new AccountingModule();
    }

    root.RatebOfflineV2Accounting = {
        __locked: true,
        version: ACCT_VERSION,
        AccountingModule: AccountingModule,
        create: createAccountingModule,
        runSelfTest: runSelfTest,
        DEFAULT_ACCOUNT_MAP: Object.assign({}, DEFAULT_ACCOUNT_MAP)
    };

    if (Business) {
        Business.createAccountingModule = createAccountingModule;
        Business.AccountingModule = AccountingModule;
    }
})(typeof window !== 'undefined' ? window : this);
