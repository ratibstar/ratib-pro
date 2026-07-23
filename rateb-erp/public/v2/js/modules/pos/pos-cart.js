/*!
 * RATEB Offline V2 — POS local cart / draft / complete / cancel (Phase 7)
 *
 * Entity types on existing entity_row:
 *   pos.sale_draft, pos.sale_line, pos.cart_session, pos.sale
 * Cancel keeps draft/sale history. No inv.* writes, sync.start(), or network push.
 */
(function (root) {
    'use strict';

    var Business = root.RatebOfflineV2Business;
    if (!Business || typeof Business.createDocStore !== 'function') {
        return;
    }

    var ET = {
        draft: 'pos.sale_draft',
        line: 'pos.sale_line',
        session: 'pos.cart_session',
        sale: 'pos.sale'
    };

    var STATUS = {
        OPEN: 'OPEN',
        COMPLETED: 'COMPLETED',
        CANCELLED: 'CANCELLED'
    };

    var OUTBOX_OP = 'CREATE_POS_SALE';

    function nowIso() {
        return new Date().toISOString();
    }

    function uid(prefix) {
        return (prefix || 'id') + '-' + Date.now().toString(36) + '-' +
            Math.random().toString(36).slice(2, 8);
    }

    function money(n) {
        var v = Math.round(Number(n || 0) * 100) / 100;
        if (!isFinite(v)) {
            return 0;
        }
        return v;
    }

    function getSyncApi() {
        var rt = root.RatebOfflineV2Runtime;
        var sync = rt && rt.services && typeof rt.services.tryGet === 'function'
            ? rt.services.tryGet('sync')
            : null;
        if (!sync && root.RatebOfflineV2ActiveSync) {
            sync = root.RatebOfflineV2ActiveSync;
        }
        return sync;
    }

    function createPosCart(module) {
        var state = {
            store: null
        };

        function ensureStore() {
            if (state.store) {
                return Promise.resolve(state.store);
            }
            var db = module.ctx && module.ctx.db;
            if (!db) {
                return Promise.reject(new Error('pos_db_missing'));
            }
            /* First cart/checkout action opens DB — not register/activate. */
            return db.open().then(function () {
                state.store = Business.createDocStore(db, {
                    ownedPrefix: 'pos.',
                    errorCode: 'pos_forbidden_storage'
                });
                return state.store;
            });
        }

        function listLinesForDraft(store, companyId, draftId) {
            return store.list(ET.line, companyId).then(function (rows) {
                return (rows || []).map(function (r) {
                    return r.payload;
                }).filter(function (line) {
                    return line && String(line.draft_id) === String(draftId);
                }).sort(function (a, b) {
                    return Number(a.sort_order || 0) - Number(b.sort_order || 0) ||
                        String(a.id || '').localeCompare(String(b.id || ''));
                });
            });
        }

        function calcTotals(lines) {
            var subtotal = 0;
            var count = 0;
            (lines || []).forEach(function (line) {
                subtotal += money(line.line_total);
                count += 1;
            });
            return {
                line_count: count,
                subtotal: money(subtotal),
                total: money(subtotal)
            };
        }

        function persistDraftTotals(store, draft, lines) {
            var totals = calcTotals(lines);
            var next = Object.assign({}, draft, {
                line_count: totals.line_count,
                subtotal: totals.subtotal,
                total: totals.total,
                updated_at: nowIso()
            });
            return store.put(ET.draft, next.id, next, Number(next.version || 1) + 1).then(function () {
                return next;
            });
        }

        function createOpenDraft(store, idCtx) {
            var draft = {
                id: uid('draft'),
                company_id: idCtx.company_id,
                branch_id: idCtx.branch_id || 0,
                user_id: idCtx.user_id || null,
                status: STATUS.OPEN,
                currency: 'SAR',
                line_count: 0,
                subtotal: 0,
                total: 0,
                source: 'local',
                created_at: nowIso(),
                updated_at: nowIso(),
                version: 1
            };
            return store.put(ET.draft, draft.id, draft, 1).then(function () {
                return store.put(ET.session, 'active', {
                    company_id: idCtx.company_id,
                    draft_id: draft.id,
                    updated_at: nowIso()
                }, 1);
            }).then(function () {
                return draft;
            });
        }

        function ensureOpenDraft(idCtx) {
            return ensureStore().then(function (store) {
                return store.get(ET.session, 'active', idCtx.company_id).then(function (sess) {
                    var draftId = sess && sess.payload && sess.payload.draft_id;
                    if (!draftId) {
                        return createOpenDraft(store, idCtx);
                    }
                    return store.get(ET.draft, draftId, idCtx.company_id).then(function (row) {
                        if (!row || !row.payload || row.payload.status !== STATUS.OPEN) {
                            return createOpenDraft(store, idCtx);
                        }
                        return row.payload;
                    });
                });
            });
        }

        function getCart(idCtx) {
            return ensureOpenDraft(idCtx).then(function (draft) {
                return ensureStore().then(function (store) {
                    return listLinesForDraft(store, idCtx.company_id, draft.id).then(function (lines) {
                        var totals = calcTotals(lines);
                        return {
                            ok: true,
                            draft: draft,
                            lines: lines,
                            line_count: totals.line_count,
                            subtotal: totals.subtotal,
                            total: totals.total,
                            currency: draft.currency || 'SAR',
                            empty: totals.line_count === 0,
                            network: false,
                            sync: false
                        };
                    });
                });
            });
        }

        function addProduct(idCtx, product, qty) {
            var quantity = Number(qty);
            if (!isFinite(quantity) || quantity <= 0) {
                quantity = 1;
            }
            if (!product || !product.id) {
                return Promise.reject(new Error('pos_cart_product_required'));
            }
            var unitPrice = money(product.price);
            return ensureOpenDraft(idCtx).then(function (draft) {
                return ensureStore().then(function (store) {
                    return listLinesForDraft(store, idCtx.company_id, draft.id).then(function (lines) {
                        var existing = null;
                        for (var i = 0; i < lines.length; i++) {
                            if (String(lines[i].product_id) === String(product.id)) {
                                existing = lines[i];
                                break;
                            }
                        }
                        var chain;
                        if (existing) {
                            var nextQty = money(Number(existing.qty || 0) + quantity);
                            var nextLine = Object.assign({}, existing, {
                                qty: nextQty,
                                unit_price: unitPrice,
                                line_total: money(nextQty * unitPrice),
                                updated_at: nowIso()
                            });
                            chain = store.put(ET.line, nextLine.id, nextLine, Number(nextLine.version || 1) + 1)
                                .then(function () { return nextLine; });
                        } else {
                            var line = {
                                id: uid('line'),
                                company_id: idCtx.company_id,
                                draft_id: draft.id,
                                product_id: product.id,
                                sku: product.sku || '',
                                name: product.name || product.id,
                                qty: quantity,
                                unit_price: unitPrice,
                                line_total: money(quantity * unitPrice),
                                currency: product.currency || draft.currency || 'SAR',
                                sort_order: lines.length + 1,
                                source: 'local',
                                created_at: nowIso(),
                                updated_at: nowIso(),
                                version: 1
                            };
                            chain = store.put(ET.line, line.id, line, 1).then(function () {
                                return line;
                            });
                        }
                        return chain.then(function () {
                            return listLinesForDraft(store, idCtx.company_id, draft.id);
                        }).then(function (freshLines) {
                            return persistDraftTotals(store, draft, freshLines).then(function (updated) {
                                return {
                                    ok: true,
                                    draft: updated,
                                    lines: freshLines,
                                    line_count: updated.line_count,
                                    subtotal: updated.subtotal,
                                    total: updated.total,
                                    currency: updated.currency || 'SAR',
                                    empty: updated.line_count === 0
                                };
                            });
                        });
                    });
                });
            });
        }

        function removeLine(idCtx, lineId) {
            if (!lineId) {
                return Promise.reject(new Error('pos_cart_line_required'));
            }
            return ensureOpenDraft(idCtx).then(function (draft) {
                return ensureStore().then(function (store) {
                    return store.get(ET.line, String(lineId), idCtx.company_id).then(function (row) {
                        if (!row || !row.payload || String(row.payload.draft_id) !== String(draft.id)) {
                            return Promise.reject(new Error('pos_cart_line_not_found'));
                        }
                        return store.remove(ET.line, String(lineId), idCtx.company_id);
                    }).then(function () {
                        return listLinesForDraft(store, idCtx.company_id, draft.id);
                    }).then(function (lines) {
                        return persistDraftTotals(store, draft, lines).then(function (updated) {
                            return {
                                ok: true,
                                draft: updated,
                                lines: lines,
                                line_count: updated.line_count,
                                subtotal: updated.subtotal,
                                total: updated.total,
                                currency: updated.currency || 'SAR',
                                empty: updated.line_count === 0
                            };
                        });
                    });
                });
            });
        }

        function updateQuantity(idCtx, lineId, qty) {
            var quantity = Number(qty);
            if (!isFinite(quantity) || quantity <= 0) {
                return removeLine(idCtx, lineId);
            }
            return ensureOpenDraft(idCtx).then(function (draft) {
                return ensureStore().then(function (store) {
                    return store.get(ET.line, String(lineId), idCtx.company_id).then(function (row) {
                        if (!row || !row.payload || String(row.payload.draft_id) !== String(draft.id)) {
                            return Promise.reject(new Error('pos_cart_line_not_found'));
                        }
                        var line = Object.assign({}, row.payload, {
                            qty: quantity,
                            line_total: money(quantity * Number(row.payload.unit_price || 0)),
                            updated_at: nowIso()
                        });
                        return store.put(ET.line, line.id, line, Number(line.version || 1) + 1);
                    }).then(function () {
                        return listLinesForDraft(store, idCtx.company_id, draft.id);
                    }).then(function (lines) {
                        return persistDraftTotals(store, draft, lines).then(function (updated) {
                            return {
                                ok: true,
                                draft: updated,
                                lines: lines,
                                line_count: updated.line_count,
                                subtotal: updated.subtotal,
                                total: updated.total,
                                currency: updated.currency || 'SAR',
                                empty: updated.line_count === 0
                            };
                        });
                    });
                });
            });
        }

        function linePayloadForSale(line, saleId) {
            return {
                id: line.id,
                sale_id: saleId,
                draft_id: line.draft_id,
                product_id: line.product_id,
                sku: line.sku || '',
                name: line.name || '',
                qty: line.qty,
                unit_price: line.unit_price,
                line_total: line.line_total,
                currency: line.currency || 'SAR',
                sort_order: line.sort_order
            };
        }

        function enqueuePosSale(salePayload) {
            var sync = getSyncApi();
            if (!sync || typeof sync.enqueue !== 'function') {
                return Promise.reject(new Error('pos_sync_not_ready'));
            }
            /* Local outbox only — never sync.start() / push / network. */
            return sync.enqueue({
                module: 'pos',
                action: OUTBOX_OP,
                entityType: ET.sale,
                entityId: String(salePayload.id),
                version: 1,
                data: salePayload,
                idempotencyKey: 'pos:' + OUTBOX_OP + ':' + salePayload.id
            }).then(function (enq) {
                return {
                    ok: !!(enq && enq.ok),
                    client_id: enq && enq.clientId,
                    idempotency_key: enq && enq.idempotencyKey,
                    entity_type: ET.sale,
                    entity_id: String(salePayload.id),
                    operation: OUTBOX_OP,
                    payload: salePayload,
                    status: 'pending',
                    sync_started: !!(sync.isStarted && sync.isStarted())
                };
            });
        }

        function readOutboxRow(clientId) {
            var db = module.ctx && module.ctx.db;
            if (!db || !clientId || typeof db.exec !== 'function') {
                return Promise.resolve(null);
            }
            return db.exec(
                'SELECT client_id, module, action, payload_json, status, attempts, created_at ' +
                'FROM sync_outbox WHERE client_id=? LIMIT 1',
                [clientId]
            ).then(function (rows) {
                var row = rows && rows[0];
                if (!row) {
                    return null;
                }
                var payload = null;
                try {
                    payload = JSON.parse(row.payload_json || '{}');
                } catch (e) {
                    payload = null;
                }
                return {
                    client_id: row.client_id,
                    module: row.module,
                    operation: row.action,
                    entity_type: payload && payload.entity_type,
                    entity_id: payload && payload.entity_id,
                    payload: payload,
                    status: row.status,
                    attempts: row.attempts,
                    created_at: row.created_at
                };
            });
        }

        /**
         * Complete OPEN draft → COMPLETED sale + local outbox CREATE_POS_SALE.
         * No network, no sync.start(), no inventory deduction.
         * opts.sale_id — optional pre-assigned id (Phase 6 reservation attaches first).
         */
        function completeSale(idCtx, opts) {
            opts = opts || {};
            return ensureOpenDraft(idCtx).then(function (draft) {
                return ensureStore().then(function (store) {
                    return listLinesForDraft(store, idCtx.company_id, draft.id).then(function (lines) {
                        if (!lines.length) {
                            return Promise.reject(new Error('pos_cart_empty'));
                        }
                        var totals = calcTotals(lines);
                        var completedAt = nowIso();
                        var saleId = opts.sale_id ? String(opts.sale_id) : uid('sale');
                        var localTxnNo = opts.local_txn_no || ('POS-' + String(idCtx.company_id) + '-' +
                            Date.now().toString(36).toUpperCase());
                        var saleLines = lines.map(function (line) {
                            return linePayloadForSale(line, saleId);
                        });
                        var sale = {
                            id: saleId,
                            local_txn_no: localTxnNo,
                            draft_id: draft.id,
                            company_id: idCtx.company_id,
                            branch_id: idCtx.branch_id || 0,
                            user_id: idCtx.user_id || null,
                            status: STATUS.COMPLETED,
                            currency: draft.currency || 'SAR',
                            line_count: totals.line_count,
                            subtotal: totals.subtotal,
                            total: totals.total,
                            lines: saleLines,
                            product_ids: saleLines.map(function (l) { return l.product_id; }),
                            reservation_ids: opts.reservation_ids || [],
                            stock_reserved: !!opts.stock_reserved,
                            inventory_deducted: false,
                            payment: null,
                            receipt: null,
                            synced: false,
                            source: 'local',
                            completed_at: completedAt,
                            created_at: draft.created_at || completedAt,
                            updated_at: completedAt,
                            version: 1
                        };
                        var doneDraft = Object.assign({}, draft, {
                            status: STATUS.COMPLETED,
                            sale_id: saleId,
                            local_txn_no: localTxnNo,
                            line_count: totals.line_count,
                            subtotal: totals.subtotal,
                            total: totals.total,
                            completed_at: completedAt,
                            updated_at: completedAt,
                            payment: null,
                            receipt: null,
                            inventory_deducted: false,
                            synced: false,
                            outbox_operation: OUTBOX_OP
                        });

                        var lineChain = Promise.resolve();
                        saleLines.forEach(function (sl, idx) {
                            var src = lines[idx];
                            lineChain = lineChain.then(function () {
                                var nextLine = Object.assign({}, src, {
                                    sale_id: saleId,
                                    updated_at: completedAt
                                });
                                return store.put(ET.line, nextLine.id, nextLine, Number(nextLine.version || 1) + 1);
                            });
                        });

                        return lineChain
                            .then(function () {
                                return store.put(ET.draft, doneDraft.id, doneDraft, Number(doneDraft.version || 1) + 1);
                            })
                            .then(function () {
                                return store.put(ET.sale, sale.id, sale, 1);
                            })
                            .then(function () {
                                return store.put(ET.session, 'active', {
                                    company_id: idCtx.company_id,
                                    draft_id: null,
                                    last_completed_draft_id: doneDraft.id,
                                    last_sale_id: saleId,
                                    last_local_txn_no: localTxnNo,
                                    updated_at: completedAt
                                }, 1);
                            })
                            .then(function () {
                                return enqueuePosSale(sale);
                            })
                            .then(function (outbox) {
                                sale.outbox_client_id = outbox.client_id || null;
                                sale.outbox_operation = OUTBOX_OP;
                                sale.outbox_status = 'pending';
                                return store.put(ET.sale, sale.id, sale, 2).then(function () {
                                    return readOutboxRow(outbox.client_id).then(function (row) {
                                        return {
                                            ok: true,
                                            sale: sale,
                                            draft: doneDraft,
                                            lines: saleLines,
                                            local_txn_no: localTxnNo,
                                            sale_id: saleId,
                                            completed_at: completedAt,
                                            outbox: outbox,
                                            outbox_row: row,
                                            payment: false,
                                            receipt: false,
                                            network: false,
                                            sync_started: !!(outbox && outbox.sync_started),
                                            inventory_deducted: false
                                        };
                                    });
                                });
                            });
                    });
                });
            });
        }

        /** Phase 3 compat — same as completeSale (now with outbox). */
        function completeDraftPlaceholder(idCtx) {
            return completeSale(idCtx);
        }

        function getSale(idCtx, saleId) {
            if (!saleId) {
                return Promise.reject(new Error('pos_sale_id_required'));
            }
            return ensureStore().then(function (store) {
                return store.get(ET.sale, String(saleId), idCtx.company_id).then(function (row) {
                    if (!row || !row.payload) {
                        return null;
                    }
                    return row.payload;
                });
            });
        }

        function getLastCompletedSale(idCtx) {
            return ensureStore().then(function (store) {
                return store.get(ET.session, 'active', idCtx.company_id).then(function (sess) {
                    var saleId = sess && sess.payload && sess.payload.last_sale_id;
                    if (!saleId) {
                        return null;
                    }
                    return store.get(ET.sale, String(saleId), idCtx.company_id).then(function (row) {
                        return row && row.payload ? row.payload : null;
                    });
                });
            });
        }

        function listDrafts(idCtx) {
            return ensureStore().then(function (store) {
                return store.list(ET.draft, idCtx.company_id).then(function (rows) {
                    return (rows || []).map(function (r) { return r.payload; }).filter(Boolean);
                });
            });
        }

        function listSales(idCtx) {
            return ensureStore().then(function (store) {
                return store.list(ET.sale, idCtx.company_id).then(function (rows) {
                    return (rows || []).map(function (r) { return r.payload; }).filter(Boolean);
                });
            });
        }

        function getActiveSession(idCtx) {
            return ensureStore().then(function (store) {
                return store.get(ET.session, 'active', idCtx.company_id).then(function (row) {
                    return row && row.payload ? row.payload : null;
                });
            });
        }

        /**
         * Cancel OPEN cart/draft: keep history as CANCELLED, clear active session.
         * Caller should release reservations for draft_id.
         */
        function cancelCart(idCtx, reason) {
            return ensureStore().then(function (store) {
                return store.get(ET.session, 'active', idCtx.company_id).then(function (sess) {
                    var draftId = sess && sess.payload && sess.payload.draft_id;
                    if (!draftId) {
                        return {
                            ok: true,
                            cancelled: false,
                            draft: null,
                            reason: 'no_active_draft'
                        };
                    }
                    return store.get(ET.draft, String(draftId), idCtx.company_id).then(function (row) {
                        if (!row || !row.payload) {
                            return store.put(ET.session, 'active', {
                                company_id: idCtx.company_id,
                                draft_id: null,
                                updated_at: nowIso()
                            }, 1).then(function () {
                                return { ok: true, cancelled: false, draft: null, reason: 'draft_missing' };
                            });
                        }
                        var draft = row.payload;
                        if (draft.status !== STATUS.OPEN) {
                            return {
                                ok: true,
                                cancelled: false,
                                draft: draft,
                                reason: 'draft_not_open'
                            };
                        }
                        return listLinesForDraft(store, idCtx.company_id, draft.id).then(function (lines) {
                            var cancelledAt = nowIso();
                            var next = Object.assign({}, draft, {
                                status: STATUS.CANCELLED,
                                cancelled_at: cancelledAt,
                                cancel_reason: reason || 'cart_cancel',
                                line_count: lines.length,
                                updated_at: cancelledAt,
                                audit: {
                                    action: 'cart_cancel',
                                    previous_status: STATUS.OPEN,
                                    at: cancelledAt,
                                    keep_history: true
                                }
                            });
                            return store.put(ET.draft, next.id, next, Number(next.version || 1) + 1)
                                .then(function () {
                                    return store.put(ET.session, 'active', {
                                        company_id: idCtx.company_id,
                                        draft_id: null,
                                        last_cancelled_draft_id: next.id,
                                        updated_at: cancelledAt
                                    }, 1);
                                })
                                .then(function () {
                                    return {
                                        ok: true,
                                        cancelled: true,
                                        draft: next,
                                        draft_id: next.id,
                                        lines: lines,
                                        keep_history: true
                                    };
                                });
                        });
                    });
                });
            });
        }

        /**
         * Cancel a local COMPLETED sale (unsynced only): audit + CANCELLED.
         * Caller releases ACTIVE reservations for sale_id.
         */
        function cancelSale(idCtx, saleId, reason) {
            if (!saleId) {
                return Promise.reject(new Error('pos_sale_id_required'));
            }
            return ensureStore().then(function (store) {
                return store.get(ET.sale, String(saleId), idCtx.company_id).then(function (row) {
                    if (!row || !row.payload) {
                        return Promise.reject(new Error('pos_sale_not_found'));
                    }
                    var sale = row.payload;
                    if (sale.status === STATUS.CANCELLED) {
                        return {
                            ok: true,
                            cancelled: false,
                            sale: sale,
                            reason: 'already_cancelled',
                            release_allowed: false
                        };
                    }
                    if (sale.status !== STATUS.COMPLETED) {
                        return Promise.reject(new Error('pos_sale_cancel_not_allowed'));
                    }
                    if (sale.synced === true) {
                        return Promise.reject(new Error('pos_sale_synced_locked'));
                    }
                    var cancelledAt = nowIso();
                    var next = Object.assign({}, sale, {
                        status: STATUS.CANCELLED,
                        cancelled_at: cancelledAt,
                        cancel_reason: reason || 'sale_cancel',
                        previous_status: STATUS.COMPLETED,
                        updated_at: cancelledAt,
                        audit: {
                            action: 'sale_cancel',
                            previous_status: STATUS.COMPLETED,
                            at: cancelledAt,
                            reservations_release_allowed: true
                        }
                    });
                    return store.put(ET.sale, next.id, next, Number(next.version || 1) + 1).then(function () {
                        return {
                            ok: true,
                            cancelled: true,
                            sale: next,
                            sale_id: next.id,
                            release_allowed: true,
                            keep_history: true
                        };
                    });
                });
            });
        }

        /**
         * OPEN drafts that are not the active session cart (abandoned).
         */
        function listAbandonedDrafts(idCtx) {
            return Promise.all([listDrafts(idCtx), getActiveSession(idCtx)]).then(function (parts) {
                var drafts = parts[0] || [];
                var sess = parts[1];
                var activeId = sess && sess.draft_id ? String(sess.draft_id) : null;
                return drafts.filter(function (d) {
                    return d && d.status === STATUS.OPEN && String(d.id) !== activeId;
                });
            });
        }

        return {
            ET: ET,
            STATUS: STATUS,
            OUTBOX_OP: OUTBOX_OP,
            ensureStore: ensureStore,
            ensureOpenDraft: ensureOpenDraft,
            getCart: getCart,
            addProduct: addProduct,
            removeLine: removeLine,
            updateQuantity: updateQuantity,
            completeSale: completeSale,
            completeDraftPlaceholder: completeDraftPlaceholder,
            getSale: getSale,
            getLastCompletedSale: getLastCompletedSale,
            listDrafts: listDrafts,
            listSales: listSales,
            getActiveSession: getActiveSession,
            cancelCart: cancelCart,
            cancelSale: cancelSale,
            listAbandonedDrafts: listAbandonedDrafts,
            isStoreOpen: function () { return !!state.store; }
        };
    }

    root.RatebOfflineV2PosCart = {
        __locked: true,
        create: createPosCart,
        entityTypes: ET,
        STATUS: STATUS,
        OUTBOX_OP: OUTBOX_OP
    };
})(typeof window !== 'undefined' ? window : this);
