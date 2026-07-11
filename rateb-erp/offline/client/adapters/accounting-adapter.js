/**
 * RATEB Offline — Accounting adapter (Phase 16B / Tier 1 drafts).
 * Queues journal / workflow / recurring / opening-balance / note drafts via enterprise offline queue.
 * Activated only when offline.enabled + offline.accounting (sub-flags gate children).
 * Does NOT enqueue posting, reverse, period close, payments, bank recon, or ZATCA.
 */
(function (root) {
    'use strict';

    function flags() {
        if (root.RatebOffline && typeof root.RatebOffline.flags === 'function') {
            return root.RatebOffline.flags() || {};
        }
        return {};
    }

    function isActive() {
        var f = flags();
        return !!(f['offline.enabled'] && f['offline.accounting']);
    }

    function isJournalsActive() {
        var f = flags();
        return !!(isActive() && f['offline.accounting.journals']);
    }

    function isWorkflowActive() {
        var f = flags();
        return !!(isActive() && f['offline.accounting.workflow']);
    }

    function isMasterDataActive() {
        var f = flags();
        return !!(isActive() && f['offline.accounting.masterdata']);
    }

    function makeClientId(prefix) {
        var rand = Math.random().toString(36).slice(2, 10);
        return String(prefix || 'acc') + '-' + Date.now() + '-' + rand;
    }

    function enqueue(action, payload, options) {
        options = options || {};
        if (!isActive()) {
            return Promise.reject(new Error('accounting_offline_disabled'));
        }
        if ((action === 'journal.create' || action === 'journal.update' || action === 'note.create'
            || action === 'recurring.create' || action === 'opening_balance.create')
            && !isJournalsActive()) {
            return Promise.reject(new Error('accounting_journals_offline_disabled'));
        }
        if (action === 'workflow.transition' && !isWorkflowActive()) {
            return Promise.reject(new Error('accounting_workflow_offline_disabled'));
        }
        var q = root.RatebOfflineQueue;
        if (!q || typeof q.enqueue !== 'function') {
            return Promise.reject(new Error('offline_queue_unavailable'));
        }
        var clientId = options.client_id || options.idempotency_key || makeClientId(action);
        return q.enqueue({
            client_id: clientId,
            idempotency_key: clientId,
            module: 'accounting',
            action: action,
            payload: payload || {},
            version: options.version || 1,
            occurred_at: options.occurred_at || new Date().toISOString()
        });
    }

    var DIRECTORY_PREFIX = {
        chart_of_accounts_directory: 'coa',
        accounting_currency_directory: 'cur',
        accounting_exchange_rate_directory: 'fx',
        accounting_tax_code_directory: 'tax',
        accounting_cost_center_directory: 'cc',
        accounting_profit_center_directory: 'pc',
        accounting_fiscal_period_directory: 'fp'
    };

    function pullDirectory(entity, options) {
        options = options || {};
        if (!isMasterDataActive()) {
            return Promise.resolve({ items: [], stub: true, disabled: true });
        }
        var pull = root.RatebOfflineDeltaPull;
        if (!pull || typeof pull.pull !== 'function') {
            return Promise.reject(new Error('delta_pull_unavailable'));
        }
        return pull.pull(entity, options).then(function (res) {
            var delta = (res && res.delta) ? res.delta : res;
            if (delta && Array.isArray(delta.items) && root.RatebOfflineSchema) {
                var prefix = DIRECTORY_PREFIX[entity] || 'acc';
                return root.RatebOfflineSchema.withStore(
                    root.RatebOfflineSchema.STORES.ENTITY_CACHE,
                    'readwrite',
                    function (store) {
                        delta.items.forEach(function (item) {
                            if (item && item.id) {
                                var cfg = root.__RATEB_ERP_SHELL_OFFLINE__ || root.__RATEB_ERP_MASTER_DATA__ || {};
                                var cid = parseInt(item.company_id || cfg.company_id, 10) || 0;
                                var bid = parseInt(
                                    item.branch_id != null ? item.branch_id : (cfg.branch_id || 0),
                                    10
                                ) || 0;
                                var id = cid + ':' + bid + ':' + prefix + ':' + item.id;
                                if (item.deleted || item.active === false) {
                                    store.delete(id);
                                    return;
                                }
                                store.put({
                                    id: id,
                                    entity: entity,
                                    company_id: cid,
                                    branch_id: bid,
                                    payload: item,
                                    data: item,
                                    updated_at: item.updated_at || null,
                                    synced_at: Date.now()
                                });
                            }
                        });
                        return delta;
                    }
                ).then(function () { return delta; }).catch(function () { return delta; });
            }
            return delta || { items: [] };
        });
    }

    root.RatebOfflineAccountingAdapter = {
        isActive: isActive,
        isJournalsActive: isJournalsActive,
        isWorkflowActive: isWorkflowActive,
        isMasterDataActive: isMasterDataActive,
        enqueue: enqueue,
        enqueueJournalCreate: function (payload, options) {
            return enqueue('journal.create', payload || {}, options);
        },
        enqueueJournalUpdate: function (payload, options) {
            return enqueue('journal.update', payload || {}, options);
        },
        enqueueWorkflowTransition: function (payload, options) {
            return enqueue('workflow.transition', payload || {}, options);
        },
        enqueueRecurringCreate: function (payload, options) {
            return enqueue('recurring.create', payload || {}, options);
        },
        enqueueOpeningBalanceCreate: function (payload, options) {
            return enqueue('opening_balance.create', payload || {}, options);
        },
        enqueueNoteCreate: function (payload, options) {
            return enqueue('note.create', payload || {}, options);
        },
        draft: function (action, payload, options) {
            return enqueue(action, payload || {}, options);
        },
        retry: function () {
            var q = root.RatebOfflineQueue;
            if (q && typeof q.flush === 'function') {
                return q.flush();
            }
            return Promise.resolve({ skipped: true });
        },
        status: function () {
            return {
                active: isActive(),
                journals: isJournalsActive(),
                workflow: isWorkflowActive(),
                masterdata: isMasterDataActive()
            };
        },
        pullChartOfAccounts: function (options) {
            return pullDirectory('chart_of_accounts_directory', options);
        },
        pullCurrencies: function (options) {
            return pullDirectory('accounting_currency_directory', options);
        },
        pullExchangeRates: function (options) {
            return pullDirectory('accounting_exchange_rate_directory', options);
        },
        pullTaxCodes: function (options) {
            return pullDirectory('accounting_tax_code_directory', options);
        },
        pullCostCenters: function (options) {
            return pullDirectory('accounting_cost_center_directory', options);
        },
        pullProfitCenters: function (options) {
            return pullDirectory('accounting_profit_center_directory', options);
        },
        pullFiscalPeriods: function (options) {
            return pullDirectory('accounting_fiscal_period_directory', options);
        },
        sync: function (options) {
            options = options || {};
            if (!isActive()) {
                return Promise.resolve({ skipped: true, disabled: true });
            }
            var q = root.RatebOfflineQueue;
            var flush = (q && typeof q.flush === 'function') ? q.flush() : Promise.resolve({ skipped: true });
            return flush.then(function (flushResult) {
                if (!isMasterDataActive()) {
                    return { flush: flushResult, directory: { stub: true }, status: root.RatebOfflineAccountingAdapter.status() };
                }
                return pullDirectory('chart_of_accounts_directory', options).then(function (directory) {
                    return { flush: flushResult, directory: directory, status: root.RatebOfflineAccountingAdapter.status() };
                });
            });
        }
    };
})(typeof window !== 'undefined' ? window : globalThis);
