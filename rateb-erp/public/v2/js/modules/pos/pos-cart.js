/*!
 * RATEB Offline V2 — POS local cart / draft sale layer (Phase 3)
 *
 * Entity types on existing entity_row: pos.sale_draft, pos.sale_line, pos.cart_session.
 * No payment, receipt, sync, inventory deduction, or network.
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
        session: 'pos.cart_session'
    };

    var STATUS = {
        OPEN: 'OPEN',
        COMPLETED: 'COMPLETED'
    };

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
            /* First cart action opens DB — not register/activate. */
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

        /**
         * Placeholder complete — marks draft COMPLETED only.
         * No payment, receipt, sync, or stock movement.
         */
        function completeDraftPlaceholder(idCtx) {
            return ensureOpenDraft(idCtx).then(function (draft) {
                return ensureStore().then(function (store) {
                    return listLinesForDraft(store, idCtx.company_id, draft.id).then(function (lines) {
                        if (!lines.length) {
                            return Promise.reject(new Error('pos_cart_empty'));
                        }
                        var done = Object.assign({}, draft, {
                            status: STATUS.COMPLETED,
                            completed_at: nowIso(),
                            updated_at: nowIso(),
                            payment: null,
                            receipt: null,
                            inventory_deducted: false,
                            synced: false
                        });
                        return store.put(ET.draft, done.id, done, Number(done.version || 1) + 1)
                            .then(function () {
                                return store.put(ET.session, 'active', {
                                    company_id: idCtx.company_id,
                                    draft_id: null,
                                    last_completed_draft_id: done.id,
                                    updated_at: nowIso()
                                }, 1);
                            })
                            .then(function () {
                                return {
                                    ok: true,
                                    draft: done,
                                    lines: lines,
                                    placeholder: true,
                                    payment: false,
                                    receipt: false,
                                    sync: false,
                                    inventory_deducted: false
                                };
                            });
                    });
                });
            });
        }

        return {
            ET: ET,
            STATUS: STATUS,
            ensureStore: ensureStore,
            ensureOpenDraft: ensureOpenDraft,
            getCart: getCart,
            addProduct: addProduct,
            removeLine: removeLine,
            updateQuantity: updateQuantity,
            completeDraftPlaceholder: completeDraftPlaceholder,
            isStoreOpen: function () { return !!state.store; }
        };
    }

    root.RatebOfflineV2PosCart = {
        __locked: true,
        create: createPosCart,
        entityTypes: ET,
        STATUS: STATUS
    };
})(typeof window !== 'undefined' ? window : this);
